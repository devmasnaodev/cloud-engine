<?php

declare(strict_types=1);

namespace App\Core\Engines\EasyEngine;

use App\Core\Engines\Contracts\EngineInterface;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Execution\RemoteCommandOptions;
use App\Core\Servers\Models\Server;

/**
 * EasyEngine engine implementation.
 *
 * Provides server capabilities using EasyEngine v4.
 * Executes commands via SSH on remote VPS servers.
 */
final class EasyEngineEngine implements EngineInterface
{
    private const ENGINE_NAME = 'easyengine';

    private const READ_TIMEOUT_SECONDS = 120;

    private const MUTATING_TIMEOUT_SECONDS = 900;

    /**
     * Supported actions by this engine.
     *
     * @var array<int, string>
     */
    private const SUPPORTED_ACTIONS = [
        'list_sites',
        'create_site',
        'delete_site',
        'site_info',
        'clean_site',
        'enable_site',
        'disable_site',
        'update_site',
    ];

    /**
     * Create a new EasyEngine engine instance.
     *
     * @param  RemoteCommandExecutorInterface  $remoteCommandExecutor  Service for remote command execution
     * @param  EasyEngineCommandBuilder  $commandBuilder  Command builder for EasyEngine
     */
    public function __construct(
        private readonly RemoteCommandExecutorInterface $remoteCommandExecutor,
        private readonly EasyEngineCommandBuilder $commandBuilder
    ) {}

    /**
     * {@inheritDoc}
     */
    public function runAction(Server $server, string $action, array $parameters = [], ?callable $onOutput = null): array
    {
        if (! $this->supportsAction($action)) {
            throw new CommandExecutionException(
                "Action '{$action}' is not supported by EasyEngine engine"
            );
        }

        if ($action === 'update_site') {
            return $this->runUpdateSiteAction($server, $parameters, $onOutput);
        }

        $command = $this->buildCommand($action, $parameters);

        return $this->executeCommand($server, $command, $action, $onOutput);
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return self::ENGINE_NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getSupportedActions(): array
    {
        return self::SUPPORTED_ACTIONS;
    }

    /**
     * {@inheritDoc}
     */
    public function supportsAction(string $action): bool
    {
        return in_array($action, self::SUPPORTED_ACTIONS, true);
    }

    /**
     * Build the command for the given action.
     *
     * Note: update_site is handled directly in runAction() via runUpdateSiteAction()
     * because it may produce multiple sequential commands.
     *
     * @param  string  $action  Action name
     * @param  array<string, mixed>  $parameters  Action parameters
     * @return string The built command
     *
     * @throws CommandExecutionException
     */
    private function buildCommand(string $action, array $parameters): string
    {
        return match ($action) {
            'list_sites' => $this->commandBuilder->buildListSites(),
            'create_site' => $this->buildCreateSiteCommand($parameters),
            'delete_site' => $this->buildDeleteSiteCommand($parameters),
            'site_info' => $this->buildSiteInfoCommand($parameters),
            'clean_site' => $this->buildCleanSiteCommand($parameters),
            'enable_site' => $this->buildToggleSiteCommand($parameters, true),
            'disable_site' => $this->buildToggleSiteCommand($parameters, false),
            default => throw new CommandExecutionException("Unknown action: {$action}"),
        };
    }

    /**
     * Build create site command with validation.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws CommandExecutionException
     */
    private function buildCreateSiteCommand(array $parameters): string
    {
        if (! isset($parameters['domain'])) {
            throw new CommandExecutionException('Domain parameter is required for create_site action');
        }

        return $this->commandBuilder->buildCreateSite(
            (string) $parameters['domain'],
            $parameters['options'] ?? []
        );
    }

    /**
     * Build delete site command with validation.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws CommandExecutionException
     */
    private function buildDeleteSiteCommand(array $parameters): string
    {
        if (! isset($parameters['domain'])) {
            throw new CommandExecutionException('Domain parameter is required for delete_site action');
        }

        return $this->commandBuilder->buildDeleteSite((string) $parameters['domain']);
    }

    /**
     * Build site info command with validation.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws CommandExecutionException
     */
    private function buildSiteInfoCommand(array $parameters): string
    {
        if (! isset($parameters['domain'])) {
            throw new CommandExecutionException('Domain parameter is required for site_info action');
        }

        return $this->commandBuilder->buildSiteInfo((string) $parameters['domain']);
    }

    /**
     * Build clean site command with validation.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws CommandExecutionException
     */
    private function buildCleanSiteCommand(array $parameters): string
    {
        if (! isset($parameters['domain'])) {
            throw new CommandExecutionException('Domain parameter is required for clean_site action');
        }

        return $this->commandBuilder->buildCleanSite((string) $parameters['domain']);
    }

    /**
     * Build toggle site command with validation.
     *
     * @param  array<string, mixed>  $parameters
     *
     * @throws CommandExecutionException
     */
    private function buildToggleSiteCommand(array $parameters, bool $enable): string
    {
        if (! isset($parameters['domain'])) {
            throw new CommandExecutionException('Domain parameter is required for toggle site action');
        }

        return $this->commandBuilder->buildToggleSite((string) $parameters['domain'], $enable);
    }

    /**
     * Execute multiple sequential update commands, one per changed option group.
     *
     * EasyEngine does not support combining multiple configuration flags in a
     * single `ee site update` invocation. Commands run in order (php →
     * proxy-cache → delete-alias → add-alias → ssl) and execution stops
     * immediately on the first failure.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{stdout: string, stderr: string, exitStatus: int}
     *
     * @throws CommandExecutionException
     */
    private function runUpdateSiteAction(Server $server, array $parameters, ?callable $onOutput): array
    {
        if (! isset($parameters['domain'])) {
            throw new CommandExecutionException('Domain parameter is required for update_site action');
        }

        $commands = $this->commandBuilder->buildUpdateSiteCommands(
            (string) $parameters['domain'],
            $parameters['options'] ?? []
        );

        if (empty($commands)) {
            throw new CommandExecutionException('No update options produced any commands.');
        }

        $combinedStdout = '';
        $combinedStderr = '';
        $lastExitStatus = 0;
        $total = count($commands);

        foreach ($commands as $index => $command) {
            if ($onOutput !== null && $index > 0) {
                $onOutput("\n\n--- [".($index + 1)."/{$total}] ---\n\n");
            }

            $result = $this->executeCommand($server, $command, 'update_site', $onOutput);

            $combinedStdout .= ($combinedStdout !== '' ? "\n\n" : '').$result['stdout'];
            if ($result['stderr'] !== '') {
                $combinedStderr .= ($combinedStderr !== '' ? "\n" : '').$result['stderr'];
            }
            $lastExitStatus = $result['exitStatus'];
        }

        return [
            'stdout' => $combinedStdout,
            'stderr' => $combinedStderr,
            'exitStatus' => $lastExitStatus,
        ];
    }

    /**
     * Execute the command via SSH driver.
     *
     * Uses nothrow RemoteCommandOptions with an action-specific timeout so the
     * executor never throws on non-zero exit status and long-running EasyEngine
     * operations are not capped by the SSH connection default.
     *
     * @param  Server  $server  Server where the command will be executed
     * @param  string  $command  Command to execute
     * @param  string  $action  Action name for error messages
     * @param  callable|null  $onOutput  Optional streaming callback for stdout chunks
     * @return array{stdout: string, stderr: string, exitStatus: int}
     *
     * @throws CommandExecutionException
     */
    private function executeCommand(Server $server, string $command, string $action, ?callable $onOutput = null): array
    {
        $result = $this->remoteCommandExecutor->run(
            $server,
            $command,
            new RemoteCommandOptions(
                nothrow: true,
                timeout: $this->resolveTimeoutForAction($action),
            ),
            $onOutput,
        );

        if ($result->exitStatus !== 0) {
            throw new CommandExecutionException(
                "EasyEngine action '{$action}' failed with exit code {$result->exitStatus}: {$result->stderr}"
            );
        }

        return [
            'stdout' => $result->stdout,
            'stderr' => $result->stderr,
            'exitStatus' => $result->exitStatus,
        ];
    }

    private function resolveTimeoutForAction(string $action): int
    {
        return match ($action) {
            'list_sites', 'site_info' => self::READ_TIMEOUT_SECONDS,
            default => self::MUTATING_TIMEOUT_SECONDS,
        };
    }
}

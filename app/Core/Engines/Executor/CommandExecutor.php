<?php

declare(strict_types=1);

namespace App\Core\Engines\Executor;

use App\Core\Engines\Contracts\EngineInterface;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Servers\Models\Server;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates command execution with validation and audit logging.
 *
 * Flow: Server + Engine + Action → Normalize → Execute → Log → Return
 */
final class CommandExecutor
{
    /**
     * Create a new command executor instance.
     *
     * @param  CommandNormalizer  $normalizer  Parameter validator and sanitizer
     * @param  LoggerInterface  $logger  Application logger
     */
    public function __construct(
        private readonly CommandNormalizer $normalizer,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute a provisioning action on a server.
     *
     * @param  Server  $server  Target server
     * @param  EngineInterface  $engine  Engine to use
     * @param  string  $action  Action to execute
     * @param  array<string, mixed>  $parameters  Action parameters
     * @return array{stdout: string, stderr: string, exitStatus: int, auditLog: CommandAuditLog}
     *
     * @throws CommandExecutionException
     */
    public function execute(
        Server $server,
        EngineInterface $engine,
        string $action,
        array $parameters = []
    ): array {
        $startTime = microtime(true);

        try {
            // Normalize action and parameters
            $normalizedAction = $this->normalizer->normalizeAction($action);
            $normalizedParameters = $this->normalizer->normalizeParameters($normalizedAction, $parameters);

            $this->logger->info('Executing provisioning action', [
                'server_id' => $server->id,
                'engine' => $engine->getName(),
                'action' => $normalizedAction,
                'parameters' => $normalizedParameters,
            ]);

            // Execute through the engine
            $result = $engine->runAction($server, $normalizedAction, $normalizedParameters);

            $duration = microtime(true) - $startTime;

            // Create audit log
            $auditLog = CommandAuditLog::create(
                serverId: $server->id,
                engine: $engine->getName(),
                action: $normalizedAction,
                command: $this->buildCommandString($normalizedAction, $normalizedParameters),
                stdout: $result['stdout'],
                stderr: $result['stderr'],
                exitStatus: $result['exitStatus'],
                duration: $duration
            );

            // Log the result
            $this->logExecution($auditLog);

            return [
                'stdout' => $result['stdout'],
                'stderr' => $result['stderr'],
                'exitStatus' => $result['exitStatus'],
                'auditLog' => $auditLog,
            ];
        } catch (CommandExecutionException $e) {
            $duration = microtime(true) - $startTime;

            // Log failed execution
            $auditLog = CommandAuditLog::create(
                serverId: $server->id,
                engine: $engine->getName(),
                action: $action,
                command: $this->buildCommandString($action, $parameters),
                stdout: '',
                stderr: $e->getMessage(),
                exitStatus: 1,
                duration: $duration
            );

            $this->logExecution($auditLog);

            throw $e;
        }
    }

    /**
     * Build a human-readable command string for logging.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function buildCommandString(string $action, array $parameters): string
    {
        $parts = [$action];

        if (isset($parameters['domain'])) {
            $parts[] = $parameters['domain'];
        }

        if (isset($parameters['options']) && is_array($parameters['options'])) {
            foreach ($parameters['options'] as $key => $value) {
                if (is_bool($value)) {
                    $parts[] = $value ? "--{$key}" : '';
                } else {
                    $parts[] = "--{$key}={$value}";
                }
            }
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Log the command execution to application logs.
     */
    private function logExecution(CommandAuditLog $auditLog): void
    {
        if ($auditLog->wasSuccessful()) {
            $this->logger->info($auditLog->getSummary(), $auditLog->toArray());
        } else {
            $this->logger->error($auditLog->getSummary(), $auditLog->toArray());
        }
    }
}

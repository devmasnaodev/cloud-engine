<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Sites;

use App\Core\Application\DTOs\Sites\SiteActionInput;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Engines\Executor\CommandNormalizer;
use App\Core\Engines\Registry\EngineRegistry;
use App\Jobs\RunSiteCommandJob;
use App\Models\Server;
use App\Models\SiteCommandRun;

/**
 * Orchestrates simple site actions that only require a domain.
 *
 * Handles: enable_site, disable_site, clean_site, delete_site.
 *
 * A single generic use case avoids four nearly identical classes while
 * keeping all dispatch logic out of the controller.
 */
final class SiteActionUseCase
{
    /** @var array<int, string> */
    private const ALLOWED_ACTIONS = [
        'enable_site',
        'disable_site',
        'clean_site',
        'delete_site',
    ];

    public function __construct(
        private readonly EngineRegistry $engineRegistry,
        private readonly CommandNormalizer $normalizer,
    ) {}

    /**
     * @throws CommandExecutionException If the action is unknown, engine is not configured,
     *                                   or domain is invalid.
     */
    public function execute(SiteActionInput $input): SiteCommandRun
    {
        if (! in_array($input->action, self::ALLOWED_ACTIONS, true)) {
            throw new CommandExecutionException(
                "SiteActionUseCase does not handle action '{$input->action}'."
            );
        }

        $eloquentServer = Server::findOrFail($input->serverId);

        if (empty($eloquentServer->provisioning_engine)) {
            throw new CommandExecutionException(
                "Server [{$eloquentServer->name}] does not have a provisioning engine configured."
            );
        }

        $this->engineRegistry->get($eloquentServer->provisioning_engine);
        $domain = $this->normalizer->normalizeDomain($input->domain);

        $command = $this->buildCommand($eloquentServer->provisioning_engine, $input->action, $domain);

        $run = SiteCommandRun::create([
            'server_id' => $eloquentServer->id,
            'site_id' => $input->siteId,
            'action' => $input->action,
            'domain' => $domain,
            'command' => $command,
            'parameters' => ['domain' => $domain],
            'status' => 'pending',
        ]);

        RunSiteCommandJob::dispatch($run->id);

        return $run;
    }

    private function buildCommand(string $engineName, string $action, string $domain): string
    {
        if ($engineName !== 'easyengine') {
            throw new CommandExecutionException(
                "No command builder registered for engine '{$engineName}'."
            );
        }

        $builder = app(\App\Core\Engines\EasyEngine\EasyEngineCommandBuilder::class);

        return match ($action) {
            'enable_site' => $builder->buildToggleSite($domain, true),
            'disable_site' => $builder->buildToggleSite($domain, false),
            'clean_site' => $builder->buildCleanSite($domain),
            'delete_site' => $builder->buildDeleteSite($domain),
            default => throw new CommandExecutionException("Unknown action '{$action}'."),
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Sites;

use App\Core\Application\DTOs\Sites\CreateSiteInput;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Engines\Executor\CommandNormalizer;
use App\Core\Engines\Registry\EngineRegistry;
use App\Jobs\RunSiteCommandJob;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteCommandRun;

/**
 * Orchestrates the creation of a new site on a remote server.
 *
 * Responsibilities:
 *   1. Resolve the provisioning engine from the registry
 *   2. Validate and normalise the domain
 *   3. Build the engine-specific command string
 *   4. Persist a pending SiteCommandRun record
 *   5. Dispatch the async execution job
 *
 * The controller is left with only HTTP concerns (validation + redirect).
 */
final class CreateSiteUseCase
{
    public function __construct(
        private readonly EngineRegistry $engineRegistry,
        private readonly CommandNormalizer $normalizer,
    ) {}

    /**
     * @throws CommandExecutionException If the engine is not configured or domain is invalid.
     */
    public function execute(CreateSiteInput $input): SiteCommandRun
    {
        $eloquentServer = Server::findOrFail($input->serverId);

        if (empty($eloquentServer->provisioning_engine)) {
            throw new CommandExecutionException(
                "Server [{$eloquentServer->name}] does not have a provisioning engine configured."
            );
        }

        $engine = $this->engineRegistry->get($eloquentServer->provisioning_engine);
        $domain = $this->normalizer->normalizeDomain($input->domain);
        $options = $input->options;

        // Ask the concrete engine's command builder for the command string.
        // We reach through the engine to its builder so the use case stays
        // engine-agnostic — it never imports EasyEngineCommandBuilder directly.
        $command = $this->buildCommand($eloquentServer->provisioning_engine, $domain, $options);

        $site = Site::updateOrCreate(
            ['server_id' => $eloquentServer->id, 'domain' => $domain],
            ['info' => null],
        );

        $run = SiteCommandRun::create([
            'server_id' => $eloquentServer->id,
            'site_id' => $site->id,
            'action' => 'create_site',
            'domain' => $domain,
            'command' => $command,
            'parameters' => ['domain' => $domain, 'options' => $options],
            'status' => 'pending',
        ]);

        RunSiteCommandJob::dispatch($run->id);

        return $run;
    }

    /**
     * Build the raw command string via the engine's command builder.
     *
     * @param  array<string, mixed>  $options
     */
    private function buildCommand(string $engineName, string $domain, array $options): string
    {
        return match ($engineName) {
            'easyengine' => app(\App\Core\Engines\EasyEngine\EasyEngineCommandBuilder::class)
                ->buildCreateSite($domain, $options),
            default => throw new CommandExecutionException(
                "No command builder registered for engine '{$engineName}'."
            ),
        };
    }
}

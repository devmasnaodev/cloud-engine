<?php

declare(strict_types=1);

namespace App\Core\Application\UseCases\Sites;

use App\Core\Application\DTOs\Sites\UpdateSiteInput;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Engines\Executor\CommandNormalizer;
use App\Core\Engines\Registry\EngineRegistry;
use App\Jobs\RunSiteCommandJob;
use App\Models\Server;
use App\Models\SiteCommandRun;

/**
 * Orchestrates updating a site configuration on a remote server.
 *
 * Validates that at least one option is provided before dispatching.
 */
final class UpdateSiteUseCase
{
    public function __construct(
        private readonly EngineRegistry $engineRegistry,
        private readonly CommandNormalizer $normalizer,
    ) {}

    /**
     * @throws CommandExecutionException If the engine is not configured, domain is invalid,
     *                                   or no options were specified.
     */
    public function execute(UpdateSiteInput $input): SiteCommandRun
    {
        if (empty($input->options)) {
            throw new CommandExecutionException('No update options were specified.');
        }

        $eloquentServer = Server::findOrFail($input->serverId);

        if (empty($eloquentServer->provisioning_engine)) {
            throw new CommandExecutionException(
                "Server [{$eloquentServer->name}] does not have a provisioning engine configured."
            );
        }

        $this->engineRegistry->get($eloquentServer->provisioning_engine);
        $domain = $this->normalizer->normalizeDomain($input->domain);
        $options = $input->options;

        $command = $this->buildCommand($eloquentServer->provisioning_engine, $domain, $options);

        $run = SiteCommandRun::create([
            'server_id' => $eloquentServer->id,
            'site_id' => $input->siteId,
            'action' => 'update_site',
            'domain' => $domain,
            'command' => $command,
            'parameters' => ['domain' => $domain, 'options' => $options],
            'status' => 'pending',
        ]);

        RunSiteCommandJob::dispatch($run->id);

        return $run;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function buildCommand(string $engineName, string $domain, array $options): string
    {
        return match ($engineName) {
            'easyengine' => implode("\n", app(\App\Core\Engines\EasyEngine\EasyEngineCommandBuilder::class)
                ->buildUpdateSiteCommands($domain, $options)),
            default => throw new CommandExecutionException(
                "No command builder registered for engine '{$engineName}'."
            ),
        };
    }
}

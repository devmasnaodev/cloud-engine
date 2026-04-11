<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Engines\Registry\EngineRegistry;
use App\Core\Servers\Models\Server as DomainServer;
use App\Models\Server;
use App\Models\Site;
use App\Models\SiteCommandRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronous job for executing site management commands via the configured engine.
 *
 * The engine is resolved at runtime from the EngineRegistry using the server's
 * `provisioning_engine` field — there are no hardcoded engine references here.
 *
 * Handles create_site, update_site, clean_site, enable_site, disable_site, and
 * delete_site actions. Streams stdout chunks to `partial_stdout` every 1.5 s for
 * live UI polling. Performs post-processing after completion (refresh site info or
 * delete Site model).
 */
final class RunSiteCommandJob implements ShouldQueue
{
    use Queueable;

    /** Maximum seconds the job may run (site creation with SSL can take ~10 min). */
    public int $timeout = 1800;

    /** Do not retry — site operations are not idempotent. */
    public int $tries = 1;

    public function __construct(
        private readonly int $siteCommandRunId,
    ) {}

    public function handle(EngineRegistry $engineRegistry): void
    {
        $run = SiteCommandRun::findOrFail($this->siteCommandRunId);
        $eloquentServer = Server::findOrFail($run->server_id);

        if (empty($eloquentServer->provisioning_engine)) {
            $run->update([
                'status' => 'failed',
                'stderr' => "Server [{$eloquentServer->name}] does not have a provisioning engine configured.",
                'exit_status' => -1,
                'completed_at' => now(),
            ]);

            return;
        }

        $engine = $engineRegistry->get($eloquentServer->provisioning_engine);
        $domainServer = DomainServer::fromEloquentModel($eloquentServer);

        $run->update(['status' => 'running', 'started_at' => now()]);

        $startTime = microtime(true);

        // Throttled output callback: flushes partial stdout every 1.5 s, capped at 20 KB.
        $state = ['lastFlush' => microtime(true), 'buffer' => ''];

        $onOutput = function (string $chunk) use ($run, &$state): void {
            $state['buffer'] .= $chunk;

            if (microtime(true) - $state['lastFlush'] < 1.5) {
                return;
            }

            $partial = strlen($state['buffer']) > 20480
                ? substr($state['buffer'], -20480)
                : $state['buffer'];

            $run->update(['partial_stdout' => $partial]);
            $state['lastFlush'] = microtime(true);
        };

        try {
            $result = $engine->runAction(
                $domainServer,
                $run->action,
                $run->parameters ?? [],
                $onOutput,
            );

            $duration = microtime(true) - $startTime;

            $run->update([
                'status' => 'completed',
                'stdout' => $result['stdout'],
                'stderr' => $result['stderr'],
                'exit_status' => $result['exitStatus'],
                'duration' => $duration,
                'completed_at' => now(),
                'partial_stdout' => null,
            ]);

            $this->postProcess($run, $engineRegistry, $eloquentServer->provisioning_engine, $domainServer);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            // If delete_site fails because the site is already gone from the engine,
            // the desired end state (no site on server) is achieved — treat as success.
            if ($run->action === 'delete_site' && str_contains($e->getMessage(), 'does not exist')) {
                $run->update([
                    'status' => 'completed',
                    'stderr' => $e->getMessage(),
                    'exit_status' => 0,
                    'duration' => $duration,
                    'completed_at' => now(),
                    'partial_stdout' => null,
                ]);

                $this->postProcess($run, $engineRegistry, $eloquentServer->provisioning_engine, $domainServer);

                return;
            }

            $run->update([
                'status' => 'failed',
                'stderr' => $e->getMessage(),
                'exit_status' => -1,
                'duration' => $duration,
                'completed_at' => now(),
                'partial_stdout' => null,
            ]);
        }
    }

    /**
     * Perform post-processing after a successful command run.
     *
     * Exceptions are caught and logged but do NOT fail the run.
     */
    private function postProcess(
        SiteCommandRun $run,
        EngineRegistry $engineRegistry,
        string $engineName,
        DomainServer $domainServer,
    ): void {
        try {
            match ($run->action) {
                'create_site', 'clean_site', 'enable_site', 'disable_site', 'update_site' => $this->refreshSiteInfo($run, $engineRegistry, $engineName, $domainServer),
                'delete_site' => $this->deleteSiteModel($run),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::channel('engines')->warning("Post-processing failed for site command action '{$run->action}'", [
                'site_command_run_id' => $run->id,
                'action' => $run->action,
                'domain' => $run->domain,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch fresh site info from the engine and update the Site model.
     */
    private function refreshSiteInfo(
        SiteCommandRun $run,
        EngineRegistry $engineRegistry,
        string $engineName,
        DomainServer $domainServer,
    ): void {
        if ($run->site_id === null) {
            return;
        }

        $site = Site::find($run->site_id);

        if ($site === null) {
            return;
        }

        $engine = $engineRegistry->get($engineName);
        $infoResult = $engine->runAction($domainServer, 'site_info', ['domain' => $run->domain]);
        $decoded = json_decode($infoResult['stdout'] ?? '', true);

        if (is_array($decoded)) {
            $site->update(['info' => $decoded]);
        }
    }

    /**
     * Delete the Site Eloquent model after a successful delete_site action.
     */
    private function deleteSiteModel(SiteCommandRun $run): void
    {
        if ($run->site_id === null) {
            return;
        }

        Site::find($run->site_id)?->delete();
    }
}

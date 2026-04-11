<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Provisioning\Events\RecipeStepCompleted;
use App\Core\Provisioning\Events\RecipeStepFailed;
use App\Core\Provisioning\Events\RecipeStepStarted;
use App\Core\Provisioning\Registry\RecipeRegistry;
use App\Core\Provisioning\Runner\RecipeRunner;
use App\Models\ProvisioningRun;
use App\Models\Server;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RunProvisioningRecipeJob implements ShouldQueue
{
    use Queueable;

    /**
     * Maximum seconds the job may run (EasyEngine install can take 30+ min).
     */
    public int $timeout = 3600;

    /**
     * Do not retry automatically — provisioning is not idempotent.
     */
    public int $tries = 1;

    public function __construct(
        private readonly int $serverId,
        private readonly string $recipeId,
        private readonly int $provisioningRunId,
    ) {}

    public function handle(
        RecipeRunner $runner,
        RecipeRegistry $registry,
        Dispatcher $events,
    ): void {
        $run = ProvisioningRun::findOrFail($this->provisioningRunId);
        $eloquentServer = Server::findOrFail($this->serverId);

        $run->update(['status' => 'running', 'started_at' => now()]);

        $recipe = $registry->find($this->recipeId);

        if ($recipe === null) {
            $run->update([
                'status' => 'failed',
                'failure_reason' => "Recipe '{$this->recipeId}' not found in registry.",
                'completed_at' => now(),
            ]);

            return;
        }

        // Shared mutable state between listeners and the output callback.
        // Using an array reference avoids passing $run everywhere and lets the
        // output callback know which step is currently active.
        $state = [
            'stepId' => '',
            'stepName' => '',
            'stepIndex' => 0,
            'totalSteps' => 0,
            'buffer' => '',
            'lastFlush' => 0.0,
        ];

        $stepsBuffer = [];

        // ── Listeners ─────────────────────────────────────────────────────────

        // Fired BEFORE the step begins executing — persist the running step so the
        // frontend can show which command is active even before output arrives.
        $startedListener = function (RecipeStepStarted $event) use ($run, &$state): void {
            $state['stepId'] = $event->step->id();
            $state['stepName'] = $event->step->name();
            $state['stepIndex'] = $event->stepIndex;
            $state['totalSteps'] = $event->totalSteps;
            $state['buffer'] = '';
            $state['lastFlush'] = microtime(true);

            $run->update([
                'current_step' => [
                    'stepId' => $state['stepId'],
                    'stepName' => $state['stepName'],
                    'stepIndex' => $state['stepIndex'],
                    'totalSteps' => $state['totalSteps'],
                    'partial_stdout' => '',
                ],
            ]);
        };

        $completedListener = function (RecipeStepCompleted $event) use ($run, &$stepsBuffer): void {
            $stepsBuffer[] = $this->stepToArray(
                $event->result->stepId, $event->result->stepName,
                $event->result->description,
                $event->result->stdout, $event->result->stderr,
                $event->result->exitStatus, $event->result->duration,
                $event->result->successful, $event->result->validationError,
            );
            // Clear the live current_step indicator and persist the finished step.
            $run->update(['steps' => $stepsBuffer, 'current_step' => null]);
        };

        $failedListener = function (RecipeStepFailed $event) use ($run, &$stepsBuffer): void {
            $stepsBuffer[] = $this->stepToArray(
                $event->result->stepId, $event->result->stepName,
                $event->result->description,
                $event->result->stdout, $event->result->stderr,
                $event->result->exitStatus, $event->result->duration,
                $event->result->successful, $event->result->validationError,
            );
            $run->update(['steps' => $stepsBuffer, 'current_step' => null]);
        };

        $events->listen(RecipeStepStarted::class, $startedListener);
        $events->listen(RecipeStepCompleted::class, $completedListener);
        $events->listen(RecipeStepFailed::class, $failedListener);

        // ── Output callback ───────────────────────────────────────────────────

        // Called by RecipeRunner → RemoteCommandExecutor → SSHDriver for every
        // stdout chunk arriving from the remote process. Flushes to DB at most
        // every 1.5 s to avoid overwhelming the database with writes.
        // Partial output is capped at 20 KB to keep individual rows small.
        $outputCallback = function (string $chunk) use ($run, &$state): void {
            $state['buffer'] .= $chunk;

            $now = microtime(true);

            if (($now - $state['lastFlush']) < 1.5) {
                return;
            }

            $partial = $state['buffer'];

            if (strlen($partial) > 20000) {
                $partial = substr($partial, -20000);
            }

            $run->update([
                'current_step' => [
                    'stepId' => $state['stepId'],
                    'stepName' => $state['stepName'],
                    'stepIndex' => $state['stepIndex'],
                    'totalSteps' => $state['totalSteps'],
                    'partial_stdout' => $partial,
                ],
            ]);

            $state['lastFlush'] = $now;
        };

        // ── Execution ─────────────────────────────────────────────────────────

        try {
            $domainServer = $eloquentServer->toDomainModel();
            if (is_string($run->execution_username) && $run->execution_username !== '') {
                $domainServer = $domainServer->withExecutionUsername($run->execution_username);
            }
            $result = $runner->run($recipe, $domainServer, $outputCallback);

            // Steps are persisted incrementally via listeners above.
            // Only update the final status/duration fields here.
            $run->update([
                'status' => $result->isSuccessful() ? 'completed' : 'failed',
                'failure_reason' => $result->failureReason,
                'total_duration' => $result->totalDuration,
                'current_step' => null,
                'completed_at' => now(),
            ]);

            if ($result->isSuccessful()) {
                $this->applyPostRunServerUpdates($eloquentServer);
            }
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
                'current_step' => null,
                'completed_at' => now(),
            ]);
        } finally {
            // Always deregister listeners — prevents memory leaks and stale closures
            // in long-running queue workers that process many jobs.
            $events->forget(RecipeStepStarted::class);
            $events->forget(RecipeStepCompleted::class);
            $events->forget(RecipeStepFailed::class);
        }
    }

    /**
     * Apply server model updates that follow specific recipe completions:
     *
     * - create-non-root-user  → add 'easyengine' to ssh_users (copying root key)
     * - install-easyengine    → set provisioning_engine + ssh_execution_username
     */
    private function applyPostRunServerUpdates(Server $server): void
    {
        match ($this->recipeId) {
            'create-non-root-user' => $this->addEasyEngineUser($server),
            'install-easyengine' => $this->activateEasyEngine($server),
            default => null,
        };
    }

    private function addEasyEngineUser(Server $server): void
    {
        $sshUsers = is_array($server->ssh_users) ? $server->ssh_users : [];
        $username = 'easyengine';

        // Already configured
        $exists = collect($sshUsers)->contains(fn ($u) => ($u['username'] ?? '') === $username);

        if ($exists) {
            return;
        }

        // Copy root's encrypted private key — easyengine user was created with root's authorized_keys
        $rootUser = collect($sshUsers)->firstWhere('username', 'root');

        if ($rootUser === null || empty($rootUser['encrypted_private_key'])) {
            return;
        }

        $sshUsers[] = [
            'username' => $username,
            'encrypted_private_key' => $rootUser['encrypted_private_key'],
        ];

        $server->update(['ssh_users' => $sshUsers]);
    }

    private function activateEasyEngine(Server $server): void
    {
        $updates = [
            'provisioning_engine' => 'easyengine',
        ];

        // Set execution user to easyengine if that user exists in ssh_users
        $sshUsers = is_array($server->ssh_users) ? $server->ssh_users : [];
        $hasEasyEngineUser = collect($sshUsers)->contains(fn ($u) => ($u['username'] ?? '') === 'easyengine');

        if ($hasEasyEngineUser) {
            $updates['ssh_execution_username'] = 'easyengine';
        }

        $server->update($updates);
    }

    /**
     * @return array<string, mixed>
     */
    private function stepToArray(
        string $stepId,
        string $stepName,
        string $description,
        string $stdout,
        string $stderr,
        int $exitStatus,
        float $duration,
        bool $successful,
        ?string $validationError,
    ): array {
        return [
            'stepId' => $stepId,
            'stepName' => $stepName,
            'description' => $description,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exitStatus' => $exitStatus,
            'duration' => $duration,
            'successful' => $successful,
            'validationError' => $validationError,
        ];
    }
}

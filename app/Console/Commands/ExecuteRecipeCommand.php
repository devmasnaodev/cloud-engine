<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\ServerPrompt;
use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Provisioning\Events\RecipeCompleted;
use App\Core\Provisioning\Events\RecipeStarted;
use App\Core\Provisioning\Events\RecipeStepCompleted;
use App\Core\Provisioning\Events\RecipeStepFailed;
use App\Core\Provisioning\Events\RecipeStepStarted;
use App\Core\Provisioning\Registry\RecipeRegistry;
use App\Core\Provisioning\Runner\RecipeRunner;
use App\Core\Servers\Services\ServerInfoDetector;
use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;

final class ExecuteRecipeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'server:recipe
                            {server_id? : ID of the server}
                            {recipe=initial-server-setup : Recipe ID to run}
                            {--select : Interactively select the recipe to run}
                            {--execution-user= : SSH user to execute the recipe as (only for recipes that allow it)}';

    /**
     * @var string
     */
    protected $description = 'Execute a provisioning recipe against a server';

    public function handle(
        ServerPrompt $serverPrompt,
        RecipeRunner $runner,
        RecipeRegistry $registry,
        ServerInfoDetector $serverInfoDetector,
        Dispatcher $events,
    ): int {
        $serverId = $this->argument('server_id');
        $recipeName = (string) $this->argument('recipe');

        // Resolve Eloquent server
        if ($serverId === null) {
            $server = $serverPrompt->selectActiveServer('Select a server to provision:');

            if ($server === null) {
                return self::FAILURE;
            }
        } else {
            $server = Server::find((int) $serverId);
        }

        if (! $server) {
            $this->error("Server with ID {$serverId} not found.");

            return self::FAILURE;
        }

        // Interactive recipe selection
        if ((bool) $this->option('select')) {
            $ids = $registry->ids();

            if (empty($ids)) {
                $this->error('No recipes registered.');

                return self::FAILURE;
            }

            $recipeName = (string) $this->choice('Select a recipe to run', $ids, $recipeName ?: 0);
        }

        // Resolve recipe from registry
        $recipe = $registry->find($recipeName);

        if ($recipe === null) {
            $this->error("Unknown recipe: {$recipeName}. Available: ".implode(', ', $registry->ids()));

            return self::FAILURE;
        }

        $this->info("Server: {$server->name} ({$server->ip_address})");
        $this->info("Recipe: {$recipe->name()} — {$recipe->description()}");
        $this->newLine();

        // Pre-flight OS check
        try {
            $domainServer = $server->toDomainModel();
            $executionUsername = $this->resolveExecutionUsername(
                $recipe,
                $domainServer,
                is_string($this->option('execution-user')) ? $this->option('execution-user') : null,
            );
            $domainServer = $domainServer->withExecutionUsername($executionUsername);
            $osInfo = $serverInfoDetector->detect($domainServer);

            if (! $serverInfoDetector->isSupported($osInfo)) {
                $this->error('Unsupported server OS/version: '.json_encode($osInfo));

                return self::FAILURE;
            }

            $this->line('<fg=gray>OS: '.($osInfo['pretty_name'] ?? 'unknown').'</>');
            $this->line('<fg=gray>Execution user: '.$executionUsername.'</>');
        } catch (\Throwable $e) {
            $this->error('Failed to connect to server: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        // Register console progress listeners
        $this->registerProgressListeners($events);

        // Run
        $result = $runner->run($recipe, $domainServer);

        $this->newLine();

        if ($result->isSuccessful()) {
            $this->info(sprintf(
                '✓ Recipe completed in %.2fs (%d/%d steps)',
                $result->totalDuration,
                $result->completedSteps(),
                $result->totalSteps(),
            ));

            return self::SUCCESS;
        }

        $this->error('✗ Recipe failed: '.($result->failureReason ?? 'unknown error'));

        return self::FAILURE;
    }

    private function registerProgressListeners(Dispatcher $events): void
    {
        $events->listen(RecipeStarted::class, function (RecipeStarted $event): void {
            $this->line(sprintf(
                '<fg=cyan>Starting "%s" (%d steps)...</>',
                $event->recipe->name(),
                $event->totalSteps,
            ));
        });

        $events->listen(RecipeStepStarted::class, function (RecipeStepStarted $event): void {
            $this->newLine();
            $this->line(sprintf(
                '<fg=yellow>[%d/%d]</> <options=bold>%s</>',
                $event->stepIndex,
                $event->totalSteps,
                $event->step->name(),
            ));
            $this->line('<fg=gray>  '.$event->step->description().'</>');
        });

        $events->listen(RecipeStepCompleted::class, function (RecipeStepCompleted $event): void {
            $r = $event->result;

            if (! empty($r->stdout)) {
                $this->line($r->stdout);
            }

            if (! empty($r->stderr)) {
                $this->line('<fg=red>'.$r->stderr.'</>');
            }

            if ($r->hasWarning()) {
                $this->warn('  ⚠ '.trim(substr((string) $r->validationError, 5)));
            }

            if ($r->isSkipped()) {
                $this->line('<fg=gray>  → Skipped: '.trim(substr((string) $r->validationError, 5)).'</>');
            }

            $this->line(sprintf('<fg=green>  ✓ done</> <fg=gray>(exit: %d, %.2fs)</>', $r->exitStatus, $r->duration));
        });

        $events->listen(RecipeStepFailed::class, function (RecipeStepFailed $event): void {
            $r = $event->result;

            if (! empty($r->stdout)) {
                $this->line($r->stdout);
            }

            if (! empty($r->stderr)) {
                $this->line('<fg=red>'.$r->stderr.'</>');
            }

            $this->error('  ✗ '.$event->reason);
        });

        $events->listen(RecipeCompleted::class, function (RecipeCompleted $event): void {
            // Outcome is printed by handle() after run() returns
        });
    }

    private function resolveExecutionUsername(
        ProvisioningRecipeInterface $recipe,
        \App\Core\Servers\Models\Server $server,
        ?string $requestedExecutionUsername,
    ): string {
        $requestedExecutionUsername = $requestedExecutionUsername !== null
            ? trim($requestedExecutionUsername)
            : null;

        if ($requestedExecutionUsername === '') {
            $requestedExecutionUsername = null;
        }

        if (! $recipe->allowsExecutionUserSelection()) {
            if ($requestedExecutionUsername !== null) {
                throw new \RuntimeException(sprintf(
                    'Recipe "%s" must always run as %s.',
                    $recipe->name(),
                    $recipe->defaultExecutionUsername(),
                ));
            }

            $requestedExecutionUsername = $recipe->defaultExecutionUsername();
        }

        $effectiveExecutionUsername = $requestedExecutionUsername ?? $recipe->defaultExecutionUsername();

        if (! $server->hasSshUser($effectiveExecutionUsername)) {
            throw new \RuntimeException(sprintf(
                'SSH user "%s" is not configured for server "%s".',
                $effectiveExecutionUsername,
                $server->name,
            ));
        }

        return $effectiveExecutionUsername;
    }
}

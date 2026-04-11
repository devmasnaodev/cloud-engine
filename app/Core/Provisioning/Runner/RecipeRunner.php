<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Runner;

use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Provisioning\Contracts\RecipeRunnerInterface;
use App\Core\Provisioning\Events\RecipeCompleted;
use App\Core\Provisioning\Events\RecipeStarted;
use App\Core\Provisioning\Events\RecipeStepCompleted;
use App\Core\Provisioning\Events\RecipeStepFailed;
use App\Core\Provisioning\Events\RecipeStepStarted;
use App\Core\Provisioning\Result\RecipeResult;
use App\Core\Provisioning\Result\StepResult;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Execution\RemoteCommandOptions;
use App\Core\Servers\Models\Server;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Executes a provisioning recipe step-by-step against a remote server.
 *
 * The runner is transport-agnostic: it fires Laravel events at each stage
 * so both console commands and web controllers can react via listeners
 * without the runner knowing anything about output formatting or broadcasting.
 *
 * Validation prefixes respected from CommandInterface::validateResult():
 *  - STOP:  → halt execution gracefully (treated as success)
 *  - SKIP:  → skip this step and continue
 *  - WARN:  → emit warning and continue
 *  - other  → abort recipe with failure
 */
final class RecipeRunner implements RecipeRunnerInterface
{
    public function __construct(
        private readonly RemoteCommandExecutorInterface $executor,
        private readonly Dispatcher $events,
    ) {}

    public function run(ProvisioningRecipeInterface $recipe, Server $server, ?callable $onStepOutput = null): RecipeResult
    {
        $steps = $recipe->steps();
        $totalSteps = count($steps);
        $stepResults = [];
        $recipeStart = microtime(true);

        $this->events->dispatch(new RecipeStarted($recipe, $server, $totalSteps));

        foreach ($steps as $index => $step) {
            $stepIndex = $index + 1;

            $this->events->dispatch(
                new RecipeStepStarted($recipe, $server, $step, $stepIndex, $totalSteps),
            );

            $stepStart = microtime(true);

            // Build per-step output callback: captures the step reference so callers
            // can distinguish which step produced which chunk.
            $stepOutputCallback = $onStepOutput !== null
                ? static function (string $chunk) use ($onStepOutput, $step): void {
                    $onStepOutput($chunk, $step);
                }
            : null;

            try {
                $stepArray = $step->toArray();
                $options = $this->buildOptions($stepArray);

                $sshResult = $this->executor->run($server, $step->command(), $options, $stepOutputCallback);
                $duration = round(microtime(true) - $stepStart, 4);

                $validationError = $step->validateResult($sshResult);

                $stepResult = new StepResult(
                    stepId: $step->id(),
                    stepName: $step->name(),
                    description: $step->description(),
                    stdout: $sshResult->stdout,
                    stderr: $sshResult->stderr,
                    exitStatus: $sshResult->exitStatus,
                    duration: $duration,
                    successful: $sshResult->exitStatus === 0 && $validationError === null,
                    validationError: $validationError,
                );

                $stepResults[] = $stepResult;

                if ($validationError !== null) {
                    if (str_starts_with($validationError, 'STOP:')) {
                        $reason = trim(substr($validationError, 5));
                        $this->events->dispatch(
                            new RecipeStepFailed($recipe, $server, $step, $stepIndex, $totalSteps, $stepResult, $reason),
                        );

                        return $this->finishRecipe($recipe, $server, $stepResults, $recipeStart, true, null);
                    }

                    if (str_starts_with($validationError, 'SKIP:')) {
                        $this->events->dispatch(
                            new RecipeStepCompleted($recipe, $server, $step, $stepIndex, $totalSteps, $stepResult),
                        );

                        continue;
                    }

                    if (str_starts_with($validationError, 'WARN:')) {
                        $this->events->dispatch(
                            new RecipeStepCompleted($recipe, $server, $step, $stepIndex, $totalSteps, $stepResult),
                        );

                        continue;
                    }

                    $this->events->dispatch(
                        new RecipeStepFailed($recipe, $server, $step, $stepIndex, $totalSteps, $stepResult, $validationError),
                    );

                    return $this->finishRecipe(
                        $recipe, $server, $stepResults, $recipeStart, false,
                        sprintf('Step "%s" failed validation: %s', $step->name(), $validationError),
                    );
                }

                if ($sshResult->exitStatus !== 0) {
                    $reason = sprintf(
                        'Step "%s" exited with status %d',
                        $step->name(),
                        $sshResult->exitStatus,
                    );

                    $this->events->dispatch(
                        new RecipeStepFailed($recipe, $server, $step, $stepIndex, $totalSteps, $stepResult, $reason),
                    );

                    return $this->finishRecipe($recipe, $server, $stepResults, $recipeStart, false, $reason);
                }

                $this->events->dispatch(
                    new RecipeStepCompleted($recipe, $server, $step, $stepIndex, $totalSteps, $stepResult),
                );
            } catch (\Throwable $e) {
                $duration = round(microtime(true) - $stepStart, 4);

                $stepResult = new StepResult(
                    stepId: $step->id(),
                    stepName: $step->name(),
                    description: $step->description(),
                    stdout: '',
                    stderr: $e->getMessage(),
                    exitStatus: -1,
                    duration: $duration,
                    successful: false,
                    validationError: $e->getMessage(),
                );

                $stepResults[] = $stepResult;

                $this->events->dispatch(
                    new RecipeStepFailed($recipe, $server, $step, $stepIndex, $totalSteps, $stepResult, $e->getMessage()),
                );

                return $this->finishRecipe(
                    $recipe, $server, $stepResults, $recipeStart, false,
                    sprintf('Step "%s" threw an exception: %s', $step->name(), $e->getMessage()),
                );
            }
        }

        return $this->finishRecipe($recipe, $server, $stepResults, $recipeStart, true, null);
    }

    /**
     * @param  StepResult[]  $stepResults
     */
    private function finishRecipe(
        ProvisioningRecipeInterface $recipe,
        Server $server,
        array $stepResults,
        float $recipeStart,
        bool $successful,
        ?string $failureReason,
    ): RecipeResult {
        $result = new RecipeResult(
            successful: $successful,
            stepResults: $stepResults,
            totalDuration: round(microtime(true) - $recipeStart, 4),
            failureReason: $failureReason,
        );

        $this->events->dispatch(new RecipeCompleted($recipe, $server, $result));

        return $result;
    }

    /**
     * Build RemoteCommandOptions from a step array.
     * Respects timeout and env exported by CommandInterface::toArray().
     *
     * @param  array<string, mixed>  $stepArray
     */
    private function buildOptions(array $stepArray): RemoteCommandOptions
    {
        if (empty($stepArray['timeout']) && empty($stepArray['env'])) {
            return RemoteCommandOptions::raw();
        }

        return new RemoteCommandOptions(
            env: $stepArray['env'] ?? [],
            nothrow: true,
            timeout: isset($stepArray['timeout']) ? (int) $stepArray['timeout'] : null,
        );
    }
}

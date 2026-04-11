<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Contracts;

use App\Core\Provisioning\Result\RecipeResult;
use App\Core\Servers\Models\Server;

/**
 * Contract for the recipe runner.
 *
 * The runner executes a recipe against a server, fires progress events at
 * each stage, and returns an aggregate RecipeResult.
 */
interface RecipeRunnerInterface
{
    /**
     * Execute all steps of the given recipe against the server.
     *
     * Fires the following events during execution:
     *  - RecipeStarted
     *  - RecipeStepStarted (per step)
     *  - RecipeStepCompleted | RecipeStepFailed (per step)
     *  - RecipeCompleted
     *
     * @param  callable|null  $onStepOutput  fn(string $chunk, CommandInterface $step): void
     *                                       Called with each stdout chunk during step execution.
     */
    public function run(ProvisioningRecipeInterface $recipe, Server $server, ?callable $onStepOutput = null): RecipeResult;
}

<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Events;

use App\Core\Commands\CommandInterface;
use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Provisioning\Result\StepResult;
use App\Core\Servers\Models\Server;

/**
 * Fired after a recipe step completes successfully (exit status 0, validation passed).
 */
final class RecipeStepCompleted
{
    public function __construct(
        public readonly ProvisioningRecipeInterface $recipe,
        public readonly Server $server,
        public readonly CommandInterface $step,
        public readonly int $stepIndex,
        public readonly int $totalSteps,
        public readonly StepResult $result,
    ) {}
}

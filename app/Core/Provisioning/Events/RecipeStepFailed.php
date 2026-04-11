<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Events;

use App\Core\Commands\CommandInterface;
use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Provisioning\Result\StepResult;
use App\Core\Servers\Models\Server;

/**
 * Fired when a step exits with a non-zero status, fails validation, or throws an exception.
 *
 * Also fired for STOP: prefixed validation messages (graceful early termination).
 */
final class RecipeStepFailed
{
    public function __construct(
        public readonly ProvisioningRecipeInterface $recipe,
        public readonly Server $server,
        public readonly CommandInterface $step,
        public readonly int $stepIndex,
        public readonly int $totalSteps,
        public readonly StepResult $result,
        public readonly string $reason,
    ) {}
}

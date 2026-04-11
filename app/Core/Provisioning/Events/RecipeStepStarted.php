<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Events;

use App\Core\Commands\CommandInterface;
use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Servers\Models\Server;

/**
 * Fired just before a recipe step begins remote execution.
 * Use this event to display progress indicators in the UI or console.
 */
final class RecipeStepStarted
{
    public function __construct(
        public readonly ProvisioningRecipeInterface $recipe,
        public readonly Server $server,
        public readonly CommandInterface $step,
        public readonly int $stepIndex,
        public readonly int $totalSteps,
    ) {}
}

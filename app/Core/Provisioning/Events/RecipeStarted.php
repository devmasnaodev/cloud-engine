<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Events;

use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Servers\Models\Server;

/**
 * Fired when a recipe begins execution.
 */
final class RecipeStarted
{
    public function __construct(
        public readonly ProvisioningRecipeInterface $recipe,
        public readonly Server $server,
        public readonly int $totalSteps,
    ) {}
}

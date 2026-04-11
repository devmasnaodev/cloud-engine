<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Events;

use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Provisioning\Result\RecipeResult;
use App\Core\Servers\Models\Server;

/**
 * Fired when a recipe finishes — whether successfully or not.
 *
 * Always fired as the final event of a recipe run, even on failure.
 * Check `$event->result->isSuccessful()` to determine outcome.
 */
final class RecipeCompleted
{
    public function __construct(
        public readonly ProvisioningRecipeInterface $recipe,
        public readonly Server $server,
        public readonly RecipeResult $result,
    ) {}
}

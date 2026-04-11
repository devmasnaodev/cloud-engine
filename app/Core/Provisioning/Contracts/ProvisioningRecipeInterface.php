<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Contracts;

use App\Core\Commands\CommandInterface;

/**
 * Contract for all server provisioning recipes.
 *
 * A recipe is an ordered collection of commands that achieves a specific
 * provisioning goal (e.g. initial server setup, engine installation).
 * Recipes are declarative data holders — they do not execute commands.
 */
interface ProvisioningRecipeInterface
{
    /**
     * Unique machine-readable identifier used for lookup via RecipeRegistry.
     *
     * Example: 'install-easyengine'
     */
    public function id(): string;

    /**
     * Human-readable recipe name shown in UIs and logs.
     */
    public function name(): string;

    /**
     * Brief description of what this recipe accomplishes.
     */
    public function description(): string;

    /**
     * Default SSH username used to execute this recipe.
     *
     * Provisioning recipes default to root so they do not depend on the
     * server's active site-management execution user.
     */
    public function defaultExecutionUsername(): string;

    /**
     * Whether the execution user can be overridden before the recipe starts.
     *
     * Root-only recipes must return false so callers never expose a selector.
     */
    public function allowsExecutionUserSelection(): bool;

    /**
     * Return the ordered list of commands to execute, in execution order.
     *
     * @return CommandInterface[]
     */
    public function steps(): array;
}

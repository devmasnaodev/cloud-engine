<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Registry;

use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;

/**
 * Central registry of all available provisioning recipes.
 *
 * Recipes are registered in CoreServiceProvider and resolved by ID
 * in controllers, console commands and the RecipeRunner.
 *
 * Example usage:
 *   $recipe = $registry->find('install-easyengine');
 */
final class RecipeRegistry
{
    /** @var array<string, ProvisioningRecipeInterface> */
    private array $recipes = [];

    public function register(ProvisioningRecipeInterface $recipe): void
    {
        $this->recipes[$recipe->id()] = $recipe;
    }

    public function find(string $id): ?ProvisioningRecipeInterface
    {
        return $this->recipes[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->recipes[$id]);
    }

    /**
     * @return ProvisioningRecipeInterface[]
     */
    public function all(): array
    {
        return array_values($this->recipes);
    }

    /**
     * @return string[]
     */
    public function ids(): array
    {
        return array_keys($this->recipes);
    }
}

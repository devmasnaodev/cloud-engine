<?php

declare(strict_types=1);

namespace App\Core\Engines\Registry;

use App\Core\Engines\Contracts\EngineInterface;

/**
 * Central registry of all available provisioning engines.
 *
 * Engines are registered in CoreServiceProvider and resolved by their
 * string identifier (e.g. 'easyengine'). The registry is the single
 * source of truth for which engines are available at runtime — replacing
 * any hardcoded references to concrete engine classes in controllers or jobs.
 *
 * Usage:
 *   $engine = $registry->get($server->provisioningEngine);
 */
final class EngineRegistry
{
    /** @var array<string, EngineInterface> */
    private array $engines = [];

    /**
     * Register an engine under the given name.
     *
     * The name should match the value stored in `servers.provisioning_engine`
     * (e.g. 'easyengine', 'wordops').
     */
    public function register(string $name, EngineInterface $engine): void
    {
        $this->engines[$name] = $engine;
    }

    /**
     * Resolve an engine by name.
     *
     * @throws EngineNotFoundException If no engine is registered under $name.
     */
    public function get(string $name): EngineInterface
    {
        if (! isset($this->engines[$name])) {
            throw EngineNotFoundException::forName($name);
        }

        return $this->engines[$name];
    }

    /**
     * Check whether an engine is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->engines[$name]);
    }

    /**
     * Return all registered engines keyed by name.
     *
     * @return array<string, EngineInterface>
     */
    public function all(): array
    {
        return $this->engines;
    }

    /**
     * Return the names of all registered engines.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->engines);
    }
}

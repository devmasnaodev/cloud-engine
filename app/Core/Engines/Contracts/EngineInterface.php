<?php

declare(strict_types=1);

namespace App\Core\Engines\Contracts;

use App\Core\Servers\Models\Server;

/**
 * Interface for all engines.
 *
 * Each engine (EasyEngine, ServerPilot, etc.) must implement this interface
 * to provide a consistent API for server operations.
 */
interface EngineInterface
{
    /**
     * Execute an action on the given server.
     *
     * @param  Server  $server  The target server
     * @param  string  $action  The action to perform (e.g., 'list_sites', 'create_site')
     * @param  array<string, mixed>  $parameters  Action-specific parameters
     * @param  callable|null  $onOutput  fn(string $chunk): void — called with each stdout chunk
     * @return array{stdout: string, stderr: string, exitStatus: int} Command execution result
     *
     * @throws \App\Core\Engines\Exceptions\CommandExecutionException
     */
    public function runAction(Server $server, string $action, array $parameters = [], ?callable $onOutput = null): array;

    /**
     * Get the engine name identifier.
     *
     * @return string Engine name (e.g., 'easyengine', 'serverpilot')
     */
    public function getName(): string;

    /**
     * Get list of supported actions.
     *
     * @return array<int, string> List of action names
     */
    public function getSupportedActions(): array;

    /**
     * Validate if an action is supported.
     *
     * @param  string  $action  Action name to validate
     * @return bool True if action is supported
     */
    public function supportsAction(string $action): bool;
}

<?php

declare(strict_types=1);

namespace App\Core\Console;

use App\Core\Servers\Models\Server as DomainServer;
use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;

use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

final class ServerPrompt
{
    /**
     * @return Collection<int, Server>
     */
    public function getServers(bool $onlyActive = false, string $orderBy = 'name'): Collection
    {
        $query = Server::query()->orderBy($orderBy);

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * @return array<int, string>
     */
    public function getActiveServerOptions(): array
    {
        return $this->getServers(onlyActive: true, orderBy: 'name')
            ->mapWithKeys(fn (Server $server) => [$server->id => $this->formatOption($server)])
            ->all();
    }

    public function selectActiveServer(string $label = 'Select a server:'): ?Server
    {
        $servers = $this->getServers(onlyActive: true, orderBy: 'name');

        if ($servers->isEmpty()) {
            warning('No active servers found in database');

            return null;
        }

        $selectedServerId = (int) select(
            label: $label,
            options: $servers
                ->mapWithKeys(fn (Server $server) => [$server->id => $this->formatOption($server)])
                ->all(),
        );

        return $servers->firstWhere('id', $selectedServerId);
    }

    public function resolveSshExecutionUsername(
        DomainServer $server,
        ?string $forcedUsername = null,
        string $label = 'Select the SSH user to execute the command:'
    ): ?string {
        if (is_string($forcedUsername) && $forcedUsername !== '') {
            if (! $server->hasSshUser($forcedUsername)) {
                error("SSH user [{$forcedUsername}] is not configured for server {$server->name}");

                return null;
            }

            return $forcedUsername;
        }

        if ($server->sshUsers === []) {
            return null;
        }

        if (count($server->sshUsers) === 1) {
            return $server->sshUsers[0]['username'];
        }

        return (string) select(
            label: $label,
            options: $this->getSshUserOptions($server),
        );
    }

    public function withExecutionUsername(DomainServer $server, string $executionUsername): DomainServer
    {
        return new DomainServer(
            id: $server->id,
            name: $server->name,
            ipAddress: $server->ipAddress,
            sshPort: $server->sshPort,
            sshUsers: $server->sshUsers,
            sshExecutionUsername: $executionUsername,
            provisioningEngine: $server->provisioningEngine,
            isActive: $server->isActive,
            createdAt: $server->createdAt,
            lastConnectedAt: $server->lastConnectedAt,
        );
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array<int, array<int, int|string>>
     */
    public function getServerTableRows(Collection $servers): array
    {
        return $servers->map(fn (Server $server) => [
            $server->id,
            $server->name,
            $server->ip_address,
            $server->ssh_port,
            $server->provisioning_engine,
            $server->is_active ? '✓ Active' : '✗ Inactive',
            $server->last_connected_at?->diffForHumans() ?? 'Never',
        ])->all();
    }

    private function formatOption(Server $server): string
    {
        return "{$server->id}:{$server->name} ({$server->ip_address})";
    }

    /**
     * @return array<string, string>
     */
    private function getSshUserOptions(DomainServer $server): array
    {
        $options = [];

        foreach ($server->sshUsers as $sshUser) {
            $username = $sshUser['username'];
            $options[$username] = $username;
        }

        return $options;
    }
}

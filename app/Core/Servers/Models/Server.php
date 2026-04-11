<?php

declare(strict_types=1);

namespace App\Core\Servers\Models;

/**
 * Server domain entity.
 *
 * Represents a VPS server in the system. This is a pure domain model
 * without ORM annotations - persistence logic is handled separately.
 */
final class Server
{
    /**
     * Create a new server instance.
     *
     * @param  int  $id  Server unique identifier
     * @param  string  $name  Human-readable server name
     * @param  string  $ipAddress  Server IP address
     * @param  int  $sshPort  SSH port (default: 22)
     * @param  array<int, array{username: string, encrypted_private_key: string}>  $sshUsers  SSH users with encrypted private keys
     * @param  string  $sshExecutionUsername  SSH user selected to execute commands
     * @param  string|null  $provisioningEngine  Engine used for provisioning (e.g., 'easyengine'), null if not yet configured
     * @param  bool  $isActive  Whether the server is active
     * @param  \DateTimeImmutable  $createdAt  Server creation timestamp
     * @param  \DateTimeImmutable|null  $lastConnectedAt  Last successful connection timestamp
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $ipAddress,
        public readonly int $sshPort,
        public readonly array $sshUsers,
        public readonly string $sshExecutionUsername,
        public readonly ?string $provisioningEngine,
        public readonly bool $isActive,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $lastConnectedAt = null
    ) {}

    /**
     * Check if the server can be connected to.
     *
     * @return bool True if server is active
     */
    public function canConnect(): bool
    {
        return $this->isActive;
    }

    /**
     * Get the SSH connection string.
     *
     * @return string Format: username@ip:port
     */
    public function getConnectionString(): string
    {
        return sprintf('%s@%s:%d', $this->sshExecutionUsername, $this->ipAddress, $this->sshPort);
    }

    /**
     * @return array{username: string, encrypted_private_key: string}
     */
    public function getExecutionUser(): array
    {
        foreach ($this->sshUsers as $sshUser) {
            if (($sshUser['username'] ?? null) === $this->sshExecutionUsername) {
                return $sshUser;
            }
        }

        throw new \RuntimeException("Execution SSH user [{$this->sshExecutionUsername}] is not configured for server [{$this->name}]");
    }

    public function hasSshUser(string $username): bool
    {
        foreach ($this->sshUsers as $sshUser) {
            if (($sshUser['username'] ?? null) === $username) {
                return true;
            }
        }

        return false;
    }

    public function withExecutionUsername(string $username): self
    {
        if (! $this->hasSshUser($username)) {
            throw new \RuntimeException("Execution SSH user [{$username}] is not configured for server [{$this->name}]");
        }

        return new self(
            id: $this->id,
            name: $this->name,
            ipAddress: $this->ipAddress,
            sshPort: $this->sshPort,
            sshUsers: $this->sshUsers,
            sshExecutionUsername: $username,
            provisioningEngine: $this->provisioningEngine,
            isActive: $this->isActive,
            createdAt: $this->createdAt,
            lastConnectedAt: $this->lastConnectedAt,
        );
    }

    /**
     * Check if server has been connected to recently.
     *
     * @param  int  $withinMinutes  Consider connected if within this many minutes
     */
    public function wasRecentlyConnected(int $withinMinutes = 5): bool
    {
        if ($this->lastConnectedAt === null) {
            return false;
        }

        $threshold = new \DateTimeImmutable("-{$withinMinutes} minutes");

        return $this->lastConnectedAt >= $threshold;
    }

    /**
     * Get server age in days.
     */
    public function getAgeInDays(): int
    {
        $now = new \DateTimeImmutable;
        $interval = $this->createdAt->diff($now);

        return (int) $interval->days;
    }

    /**
     * Create a domain Server instance from an Eloquent model.
     *
     * @param  \App\Models\Server  $eloquentServer  Eloquent server model
     * @return self Domain server instance
     */
    public static function fromEloquentModel(\App\Models\Server $eloquentServer): self
    {
        return $eloquentServer->toDomainModel();
    }
}

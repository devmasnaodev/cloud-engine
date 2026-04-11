<?php

declare(strict_types=1);

namespace App\Core\Engines\Executor;

use Illuminate\Support\Str;

/**
 * Audit log entry for command execution.
 *
 * Stores complete information about a command execution for auditing,
 * debugging, and compliance purposes.
 */
final class CommandAuditLog
{
    /**
     * Create a new audit log entry.
     *
     * @param  string  $uuid  Unique identifier for this log entry
     * @param  int  $serverId  ID of the server where command was executed
     * @param  string  $engine  Name of the engine used
     * @param  string  $action  Action that was executed
     * @param  string  $command  Full command that was executed
     * @param  string  $stdout  Standard output from the command
     * @param  string  $stderr  Standard error output from the command
     * @param  int  $exitStatus  Command exit status code
     * @param  float  $duration  Execution duration in seconds
     * @param  \DateTimeImmutable  $executedAt  Timestamp when command was executed
     */
    public function __construct(
        public readonly string $uuid,
        public readonly int $serverId,
        public readonly string $engine,
        public readonly string $action,
        public readonly string $command,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $exitStatus,
        public readonly float $duration,
        public readonly \DateTimeImmutable $executedAt
    ) {}

    /**
     * Create a new audit log entry with auto-generated UUID.
     */
    public static function create(
        int $serverId,
        string $engine,
        string $action,
        string $command,
        string $stdout,
        string $stderr,
        int $exitStatus,
        float $duration
    ): self {
        return new self(
            uuid: Str::uuid()->toString(),
            serverId: $serverId,
            engine: $engine,
            action: $action,
            command: $command,
            stdout: $stdout,
            stderr: $stderr,
            exitStatus: $exitStatus,
            duration: $duration,
            executedAt: new \DateTimeImmutable
        );
    }

    /**
     * Check if the command execution was successful.
     *
     * @return bool True if exit status is 0
     */
    public function wasSuccessful(): bool
    {
        return $this->exitStatus === 0;
    }

    /**
     * Check if the command execution failed.
     *
     * @return bool True if exit status is non-zero
     */
    public function hasFailed(): bool
    {
        return $this->exitStatus !== 0;
    }

    /**
     * Get a summary of the audit log.
     *
     * @return string Summary string
     */
    public function getSummary(): string
    {
        $status = $this->wasSuccessful() ? 'SUCCESS' : 'FAILED';

        return sprintf(
            '[%s] %s - %s on server #%d (%.2fs)',
            $status,
            $this->engine,
            $this->action,
            $this->serverId,
            $this->duration
        );
    }

    /**
     * Convert to array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'server_id' => $this->serverId,
            'engine' => $this->engine,
            'action' => $this->action,
            'command' => $this->command,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'exit_status' => $this->exitStatus,
            'duration' => $this->duration,
            'executed_at' => $this->executedAt->format('Y-m-d H:i:s'),
        ];
    }
}

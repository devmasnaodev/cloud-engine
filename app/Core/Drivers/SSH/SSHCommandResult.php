<?php

declare(strict_types=1);

namespace App\Core\Drivers\SSH;

/**
 * Result of an SSH command execution.
 *
 * Immutable value object containing the complete result of a command
 * executed over SSH, including output streams and exit status.
 */
final class SSHCommandResult
{
    /**
     * Create a new SSH command result.
     *
     * @param  string  $command  The command that was executed
     * @param  string  $stdout  Standard output from the command
     * @param  string  $stderr  Standard error output from the command
     * @param  int  $exitStatus  Exit status code (0 = success)
     * @param  float  $duration  Execution duration in seconds
     */
    public function __construct(
        public readonly string $command,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int $exitStatus,
        public readonly float $duration
    ) {}

    /**
     * Check if command execution was successful.
     *
     * @return bool True if exit status is 0
     */
    public function isSuccessful(): bool
    {
        return $this->exitStatus === 0;
    }

    /**
     * Check if command execution failed.
     *
     * @return bool True if exit status is non-zero
     */
    public function hasFailed(): bool
    {
        return $this->exitStatus !== 0;
    }

    /**
     * Check if there is any error output.
     *
     * @return bool True if stderr is not empty
     */
    public function hasErrors(): bool
    {
        return ! empty(trim($this->stderr));
    }

    /**
     * Get the combined output (stdout + stderr).
     */
    public function getCombinedOutput(): string
    {
        $output = $this->stdout;

        if ($this->hasErrors()) {
            $output .= "\n".$this->stderr;
        }

        return trim($output);
    }

    /**
     * Get a summary of the command result.
     */
    public function getSummary(): string
    {
        $status = $this->isSuccessful() ? 'SUCCESS' : 'FAILED';

        return sprintf(
            '[%s] Command: %s (exit: %d, duration: %.2fs)',
            $status,
            $this->command,
            $this->exitStatus,
            $this->duration
        );
    }

    /**
     * Convert to array for logging or serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'command' => $this->command,
            'stdout' => $this->stdout,
            'stderr' => $this->stderr,
            'exit_status' => $this->exitStatus,
            'duration' => $this->duration,
            'successful' => $this->isSuccessful(),
        ];
    }
}

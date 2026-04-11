<?php

declare(strict_types=1);

namespace App\Core\Drivers\SSH;

use Illuminate\Support\Facades\Log;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

/**
 * SSH driver for remote command execution.
 *
 * Provides a secure interface to execute commands on remote servers via SSH
 * using phpseclib. Handles connection management, authentication, and
 * command execution with proper error handling.
 */
final class SSHDriver
{
    private ?SSH2 $connection = null;

    /**
     * Create a new SSH driver instance.
     *
     * @param  SSHConnectionConfig  $config  Connection configuration
     */
    public function __construct(
        private readonly SSHConnectionConfig $config
    ) {}

    /**
     * Establish SSH connection to the server.
     *
     *
     * @throws \RuntimeException If connection or authentication fails
     */
    public function connect(): void
    {
        if ($this->isConnected()) {
            return;
        }

        try {
            // Create SSH connection
            $this->connection = new SSH2($this->config->host, $this->config->port, $this->config->timeout);

            // Enable stderr capture
            $this->connection->enableQuietMode();

            // Load private key
            $key = PublicKeyLoader::load($this->config->privateKey);

            // Authenticate
            if (! $this->connection->login($this->config->username, $key)) {
                throw new \RuntimeException('SSH authentication failed');
            }

            // Set timeout for command execution
            $this->connection->setTimeout($this->config->timeout);

            Log::channel('engines')->info('SSH connection established', [
                'host' => $this->config->host,
                'port' => $this->config->port,
                'username' => $this->config->username,
            ]);
        } catch (\Throwable $e) {
            Log::channel('engines')->error('SSH connection failed', [
                'host' => $this->config->host,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "Failed to connect to {$this->config->getConnectionString()}: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Check if currently connected to the server.
     */
    public function isConnected(): bool
    {
        return $this->connection !== null && $this->connection->isConnected();
    }

    /**
     * Execute a command on the remote server.
     *
     * When $onOutput is provided it is called with each chunk of stdout as it
     * arrives from the remote process, enabling real-time progress reporting.
     * The full accumulated stdout is still available in the returned result.
     *
     * @param  string  $command  Command to execute
     * @param  int|null  $timeout  Per-command timeout in seconds
     * @param  string|null  $commandForLogs  Sanitised version for log lines
     * @param  callable|null  $onOutput  fn(string $chunk): void — called per stdout chunk
     * @return SSHCommandResult Command execution result
     *
     * @throws \RuntimeException If not connected or execution fails
     */
    public function execute(string $command, ?int $timeout = null, ?string $commandForLogs = null, ?callable $onOutput = null): SSHCommandResult
    {
        if (! $this->isConnected()) {
            throw new \RuntimeException('Not connected to SSH server');
        }

        $startTime = microtime(true);
        $effectiveTimeout = $timeout ?? $this->config->timeout;
        $logCommand = $commandForLogs ?? $command;

        try {
            $this->connection->setTimeout($effectiveTimeout);

            Log::channel('engines')->debug('Executing SSH command', ['command' => $logCommand]);

            // Accumulate stdout chunks delivered by phpseclib's exec callback.
            // When a callback is used, exec() may return an empty string on some
            // phpseclib versions; the chunk buffer is the reliable source of truth.
            $stdoutChunks = [];

            $wrappedCallback = function (string $data) use (&$stdoutChunks, $onOutput): void {
                $clean = $this->stripAnsi($data);
                $stdoutChunks[] = $clean;
                if ($onOutput !== null) {
                    ($onOutput)($clean);
                }
            };

            $execReturn = $this->connection->exec($command, $wrappedCallback);

            // Prefer chunked accumulation; fall back to exec return value when empty.
            $rawReturn = $stdoutChunks !== [] ? implode('', $stdoutChunks) : (string) ($execReturn ?? '');
            $stdoutStr = $this->stripAnsi($rawReturn);

            $stderr = $this->stripAnsi((string) ($this->connection->getStdError() ?: ''));

            // Get exit status - phpseclib returns false if not available
            $exitStatus = $this->connection->getExitStatus();
            if ($exitStatus === false) {
                // If exit status is not available but we have output, assume success
                $exitStatus = ($stdoutStr !== '') ? 0 : 1;
            }

            $duration = microtime(true) - $startTime;

            Log::channel('engines')->debug('SSH command completed', [
                'command' => $logCommand,
                'exit_status' => $exitStatus,
                'stdout_length' => strlen($stdoutStr),
                'stderr_length' => strlen($stderr),
                'duration' => $duration,
                'stdout_preview' => substr($stdoutStr, 0, 200),
                'stderr_preview' => substr($stderr, 0, 200),
            ]);

            return new SSHCommandResult(
                command: $command,
                stdout: $stdoutStr,
                stderr: $stderr,
                exitStatus: $exitStatus,
                duration: $duration
            );
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            Log::channel('engines')->error('SSH command exception', [
                'command' => $logCommand,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration' => $duration,
            ]);

            return new SSHCommandResult(
                command: $command,
                stdout: '',
                stderr: $e->getMessage(),
                exitStatus: 1,
                duration: $duration
            );
        }
    }

    /**
     * Execute multiple commands sequentially.
     *
     * @param  array<int, string>  $commands  Array of commands to execute
     * @return array<int, SSHCommandResult> Array of command results
     */
    public function executeMultiple(array $commands): array
    {
        $results = [];

        foreach ($commands as $command) {
            $results[] = $this->execute($command);
        }

        return $results;
    }

    /**
     * Disconnect from the server.
     */
    public function disconnect(): void
    {
        if ($this->connection !== null) {
            $this->connection->disconnect();
            $this->connection = null;
        }
    }

    /**
     * Get the connection configuration.
     */
    public function getConfig(): SSHConnectionConfig
    {
        return $this->config;
    }

    /**
     * Ensure disconnection on object destruction.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Strip ANSI escape sequences from a string.
     *
     * When commands run inside a pseudo-TTY (via `script`), programs that detect
     * a terminal emit ANSI color and formatting codes (e.g. \e[32;1mSuccess:\e[0m).
     * These sequences pollute logs, JSON parsing, and stream display in the UI.
     * Applied to every stdout chunk and to the final accumulated output.
     */
    private function stripAnsi(string $output): string
    {
        return (string) preg_replace('/\x1b\[[0-9;]*[a-zA-Z]/', '', $output);
    }
}

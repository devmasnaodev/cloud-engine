<?php

declare(strict_types=1);

namespace App\Core\Drivers\SSH;

/**
 * Configuration for SSH connections.
 *
 * Immutable value object containing all necessary information
 * to establish an SSH connection to a remote server.
 */
final class SSHConnectionConfig
{
    /**
     * Create a new SSH connection configuration.
     *
     * @param  string  $host  Server hostname or IP address
     * @param  int  $port  SSH port (default: 22)
     * @param  string  $username  SSH username
     * @param  string  $privateKey  SSH private key content
     * @param  int  $timeout  Connection timeout in seconds (default: 1800)
     * @param  bool  $verifyHost  Whether to verify host key (default: true)
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port = 22,
        public readonly string $username = 'root',
        public readonly string $privateKey = '',
        public readonly int $timeout = 1800,
        public readonly bool $verifyHost = true
    ) {
        $this->validate();
    }

    /**
     * Validate configuration parameters.
     *
     *
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        if (empty($this->host)) {
            throw new \InvalidArgumentException('Host cannot be empty');
        }

        if ($this->port < 1 || $this->port > 65535) {
            throw new \InvalidArgumentException('Port must be between 1 and 65535');
        }

        if (empty($this->username)) {
            throw new \InvalidArgumentException('Username cannot be empty');
        }

        if (empty($this->privateKey)) {
            throw new \InvalidArgumentException('Private key cannot be empty');
        }

        if ($this->timeout < 1) {
            throw new \InvalidArgumentException('Timeout must be at least 1 second');
        }
    }

    /**
     * Get the connection string representation.
     *
     * @return string Format: username@host:port
     */
    public function getConnectionString(): string
    {
        return sprintf('%s@%s:%d', $this->username, $this->host, $this->port);
    }

    /**
     * Create a configuration for testing purposes.
     */
    public static function forTesting(): self
    {
        return new self(
            host: 'localhost',
            port: 22,
            username: 'testuser',
            privateKey: "-----BEGIN OPENSSH PRIVATE KEY-----\ntest\n-----END OPENSSH PRIVATE KEY-----",
            timeout: 10,
            verifyHost: false
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Servers\Services;

use App\Core\Drivers\SSH\SSHConnectionConfig;
use App\Core\Drivers\SSH\SSHDriver;
use App\Core\Engines\Validators\EngineValidatorResolver;
use App\Core\Security\Credentials\SecretEncryptionService;
use App\Core\Servers\Contracts\ServerConnectionServiceInterface;
use App\Core\Servers\Models\Server;
use Psr\Log\LoggerInterface;

/**
 * Service for establishing SSH connections to servers.
 *
 * Handles decryption of private keys and creation of SSH driver instances.
 */
final class ServerConnectionService implements ServerConnectionServiceInterface
{
    /**
     * Create a new server connection service.
     *
     * @param  SecretEncryptionService  $encryptionService  Service for decrypting private keys
     * @param  LoggerInterface  $logger  Application logger
     */
    public function __construct(
        private readonly SecretEncryptionService $encryptionService,
        private readonly LoggerInterface $logger,
        private readonly ?EngineValidatorResolver $validatorResolver = null
    ) {}

    /**
     * Create an SSH connection to the given server.
     *
     * @param  Server  $server  Server to connect to
     * @return SSHDriver Configured and connected SSH driver
     *
     * @throws \RuntimeException If connection fails
     */
    public function connect(Server $server): SSHDriver
    {
        if (! $server->canConnect()) {
            throw new \RuntimeException("Server {$server->name} is not active and cannot be connected to");
        }

        $this->logger->info('Establishing SSH connection', [
            'server_id' => $server->id,
            'server_name' => $server->name,
            'ip' => $server->ipAddress,
            'port' => $server->sshPort,
            'execution_user' => $server->sshExecutionUsername,
        ]);

        $executionUser = $server->getExecutionUser();

        // Decrypt private key (only in-memory, never persisted)
        $privateKey = $this->encryptionService->decrypt($executionUser['encrypted_private_key']);

        // Create SSH configuration
        $config = new SSHConnectionConfig(
            host: $server->ipAddress,
            port: $server->sshPort,
            username: $executionUser['username'],
            privateKey: $privateKey,
            timeout: 30
        );

        // Create and connect SSH driver
        $driver = new SSHDriver($config);
        $driver->connect();

        $this->logger->info('SSH connection established', [
            'server_id' => $server->id,
            'connection_string' => $server->getConnectionString(),
        ]);

        return $driver;
    }

    /**
     * Test connection to a server without maintaining the connection.
     *
     * @param  Server  $server  Server to test
     * @return bool True if connection successful
     */
    public function testConnection(Server $server): bool
    {
        try {
            $driver = $this->connect($server);
            $result = $driver->execute('echo "connection_test"');
            $connected = $result->exitStatus === 0 && str_contains($result->stdout, 'connection_test');

            // If an engine validator exists for this server's provisioning engine,
            // run it and require it to pass as well. Validator returns boolean.
            $engine = $server->provisioningEngine ?? '';
            if ($connected && $engine !== '' && $this->validatorResolver !== null) {
                $validator = $this->validatorResolver->getValidator($engine);

                if ($validator !== null) {
                    $valid = false;
                    try {
                        $valid = $validator->validate($driver);
                    } catch (\Throwable $e) {
                        $this->logger->warning('Engine validation failed with exception', [
                            'server_id' => $server->id,
                            'engine' => $engine,
                            'error' => $e->getMessage(),
                        ]);
                        $valid = false;
                    }

                    $driver->disconnect();

                    return $connected && $valid;
                }
            }

            $driver->disconnect();

            return $connected;
        } catch (\Throwable $e) {
            $this->logger->warning('Connection test failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

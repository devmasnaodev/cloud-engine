<?php

declare(strict_types=1);

namespace App\Core\Servers\Services;

use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Exceptions\RemoteCommandFailedException;
use App\Core\Servers\Execution\RemoteCommandFormatter;
use App\Core\Servers\Execution\RemoteCommandOptions;
use App\Core\Servers\Models\Server;
use Psr\Log\LoggerInterface;

final class RemoteCommandExecutor implements RemoteCommandExecutorInterface
{
    public function __construct(
        private readonly ServerConnectionService $connectionService,
        private readonly RemoteCommandFormatter $formatter,
        private readonly LoggerInterface $logger
    ) {}

    public function run(Server $server, string $command, ?RemoteCommandOptions $options = null, ?callable $onOutput = null): SSHCommandResult
    {
        $resolvedOptions = $options ?? RemoteCommandOptions::raw();
        $driver = $this->connectionService->connect($server);

        try {
            $preparedCommand = $this->formatter->format($command, $resolvedOptions);
            $logCommand = $this->formatter->sanitizeForLogs($command);

            $result = $driver->execute(
                command: $preparedCommand,
                timeout: $resolvedOptions->timeout,
                commandForLogs: $logCommand,
                onOutput: $onOutput,
            );

            if (! $resolvedOptions->nothrow && $result->exitStatus !== 0) {
                throw new RemoteCommandFailedException($server, $result);
            }

            return $result;
        } finally {
            $driver->disconnect();
        }
    }

    public function runMultiple(Server $server, array $commands, ?RemoteCommandOptions $options = null): array
    {
        $resolvedOptions = $options ?? RemoteCommandOptions::raw();
        $driver = $this->connectionService->connect($server);
        $results = [];

        try {
            foreach ($commands as $command) {
                $preparedCommand = $this->formatter->format($command, $resolvedOptions);
                $logCommand = $this->formatter->sanitizeForLogs($command);

                $result = $driver->execute(
                    command: $preparedCommand,
                    timeout: $resolvedOptions->timeout,
                    commandForLogs: $logCommand,
                );

                if (! $resolvedOptions->nothrow && $result->exitStatus !== 0) {
                    throw new RemoteCommandFailedException($server, $result);
                }

                $results[] = $result;
            }

            return $results;
        } finally {
            $driver->disconnect();
        }
    }

    public function execute(Server $server, string $command): SSHCommandResult
    {
        return $this->run($server, $command, RemoteCommandOptions::raw());
    }

    public function executeMultiple(Server $server, array $commands): array
    {
        return $this->runMultiple($server, $commands, RemoteCommandOptions::raw());
    }

    public function testConnection(Server $server): bool
    {
        try {
            $result = $this->execute($server, 'echo "connection_test"');

            return $result->isSuccessful() && str_contains($result->stdout, 'connection_test');
        } catch (\Throwable $e) {
            $this->logger->warning('Remote command connection test failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\ServerPrompt;
use App\Core\Servers\Contracts\ServerConnectionServiceInterface;
use App\Models\Server;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;

final class TestServerConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:test-connection {server_id? : ID of the server to test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SSH connection to a server';

    /**
     * Execute the console command.
     */
    public function handle(ServerConnectionServiceInterface $connectionService, ServerPrompt $serverPrompt): int
    {
        $serverId = $this->argument('server_id');

        if ($serverId === null) {
            $server = $serverPrompt->selectActiveServer('Select a server to test:');

            if ($server === null) {
                return self::FAILURE;
            }
        } else {
            $server = Server::find($serverId);
        }

        if ($server === null) {
            error("Server with ID {$serverId} not found");

            return self::FAILURE;
        }

        info("Testing connection to: {$server->name} ({$server->ip_address})");

        try {
            $domainServer = $server->toDomainModel();
            $startTime = microtime(true);

            if ($connectionService->testConnection($domainServer)) {
                $duration = round((microtime(true) - $startTime) * 1000, 2);
                info("✓ Connection successful! ({$duration}ms)");

                // Update last_connected_at
                $server->update(['last_connected_at' => now()]);

                return self::SUCCESS;
            } else {
                error('✗ Connection failed');

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            error('✗ Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

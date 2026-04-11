<?php

declare(strict_types=1);

use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Models\Server as DomainServer;
use App\Core\Servers\Services\ServerInfoDetector;
use App\Models\Server;

use function Pest\Laravel\artisan;
use function Pest\Laravel\instance;

it('prompts for an active server when no server id is provided for provisioning', function () {
    $alphaServer = Server::factory()->create([
        'name' => 'Alpha',
        'ip_address' => '10.0.0.10',
        'ssh_users' => [[
            'username' => 'root',
            'encrypted_private_key' => encrypt('root-key'),
        ]],
        'ssh_execution_username' => 'root',
    ]);

    $zuluServer = Server::factory()->create([
        'name' => 'Zulu',
        'ip_address' => '10.0.0.20',
        'ssh_users' => [[
            'username' => 'root',
            'encrypted_private_key' => encrypt('root-key'),
        ]],
        'ssh_execution_username' => 'root',
    ]);

    Server::factory()->inactive()->create([
        'name' => 'Bravo',
        'ip_address' => '10.0.0.30',
    ]);

    $executor = \Mockery::mock(RemoteCommandExecutorInterface::class);
    $executor->shouldReceive('execute')
        ->once()
        ->withArgs(fn (DomainServer $server, string $command): bool => $server->id === $alphaServer->id && $command === 'cat /etc/os-release')
        ->andReturn(new SSHCommandResult(
            command: 'cat /etc/os-release',
            stdout: '',
            stderr: '',
            exitStatus: 0,
            duration: 0.01,
        ));

    instance(RemoteCommandExecutorInterface::class, $executor);
    instance(ServerInfoDetector::class, new ServerInfoDetector($executor));

    artisan('server:recipe')
        ->expectsChoice('Select a server to provision:', $alphaServer->id, [
            $alphaServer->id => "{$alphaServer->id}:{$alphaServer->name} ({$alphaServer->ip_address})",
            $zuluServer->id => "{$zuluServer->id}:{$zuluServer->name} ({$zuluServer->ip_address})",
        ])
        ->expectsOutputToContain('Server: Alpha (10.0.0.10)')
        ->expectsOutputToContain('Recipe: Initial Server Setup')
        ->assertFailed();
});

it('fails when no active server is available for provisioning selection', function () {
    Server::factory()->inactive()->create();

    artisan('server:recipe')
        ->expectsOutputToContain('No active servers found in database')
        ->assertFailed();
});

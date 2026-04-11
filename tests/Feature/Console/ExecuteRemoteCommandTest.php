<?php

use App\Core\Console\ServerPrompt;
use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Models\Server as DomainServer;
use App\Models\Server;

use function Pest\Laravel\artisan;
use function Pest\Laravel\instance;

it('builds active server options ordered by name', function () {
    $alphaServer = Server::factory()->create([
        'name' => 'Alpha',
        'ip_address' => '10.0.0.10',
    ]);

    $zuluServer = Server::factory()->create([
        'name' => 'Zulu',
        'ip_address' => '10.0.0.20',
    ]);

    Server::factory()->inactive()->create([
        'name' => 'Bravo',
        'ip_address' => '10.0.0.30',
    ]);

    expect(app(ServerPrompt::class)->getActiveServerOptions())->toBe([
        $alphaServer->id => "{$alphaServer->id}:Alpha (10.0.0.10)",
        $zuluServer->id => "{$zuluServer->id}:Zulu (10.0.0.20)",
    ]);
});

it('prompts for an active server when no server id is provided', function () {
    $alphaServer = Server::factory()->create([
        'name' => 'Alpha',
        'ip_address' => '10.0.0.10',
    ]);

    $zuluServer = Server::factory()->create([
        'name' => 'Zulu',
        'ip_address' => '10.0.0.20',
    ]);

    Server::factory()->inactive()->create([
        'name' => 'Bravo',
        'ip_address' => '10.0.0.30',
    ]);

    $selectedOption = "{$alphaServer->id}:{$alphaServer->name} ({$alphaServer->ip_address})";
    $otherOption = "{$zuluServer->id}:{$zuluServer->name} ({$zuluServer->ip_address})";

    $remoteCommandExecutor = \Mockery::mock(RemoteCommandExecutorInterface::class);
    $remoteCommandExecutor->shouldReceive('execute')
        ->once()
        ->withArgs(function (DomainServer $server, string $command) use ($alphaServer): bool {
            return $server->id === $alphaServer->id && $command === 'ls -la';
        })
        ->andReturn(new SSHCommandResult(
            command: 'ls -la',
            stdout: 'listing',
            stderr: '',
            exitStatus: 0,
            duration: 0.25,
        ));

    instance(RemoteCommandExecutorInterface::class, $remoteCommandExecutor);

    artisan('server:exec')
        ->expectsChoice('Select a server:', $alphaServer->id, [
            $alphaServer->id => $selectedOption,
            $zuluServer->id => $otherOption,
        ])
        ->expectsQuestion('Enter the command to execute:', 'ls -la')
        ->expectsOutputToContain('Executing on Alpha (10.0.0.10) as infoadm: ls -la')
        ->assertSuccessful();

    expect($alphaServer->fresh()->last_connected_at)->not->toBeNull();
});

it('prompts for ssh user selection when server has multiple users', function () {
    $server = Server::factory()->create([
        'name' => 'Omega',
        'ip_address' => '10.0.0.40',
        'ssh_users' => [
            [
                'username' => 'root',
                'encrypted_private_key' => encrypt('root-key'),
            ],
            [
                'username' => 'deploy',
                'encrypted_private_key' => encrypt('deploy-key'),
            ],
        ],
        'ssh_execution_username' => 'root',
    ]);

    $remoteCommandExecutor = \Mockery::mock(RemoteCommandExecutorInterface::class);
    $remoteCommandExecutor->shouldReceive('execute')
        ->once()
        ->withArgs(function (DomainServer $domainServer, string $command): bool {
            return $domainServer->sshExecutionUsername === 'deploy' && $command === 'whoami';
        })
        ->andReturn(new SSHCommandResult(
            command: 'whoami',
            stdout: 'deploy',
            stderr: '',
            exitStatus: 0,
            duration: 0.10,
        ));

    instance(RemoteCommandExecutorInterface::class, $remoteCommandExecutor);

    artisan('server:exec', ['server_id' => $server->id])
        ->expectsChoice('Select the SSH user to execute the command:', 'deploy', [
            'root' => 'root',
            'deploy' => 'deploy',
        ])
        ->expectsQuestion('Enter the command to execute:', 'whoami')
        ->expectsOutputToContain('Executing on Omega (10.0.0.40) as deploy: whoami')
        ->assertSuccessful();

    expect($server->fresh()->last_connected_at)->not->toBeNull();
});

it('uses the provided ssh user option without prompting', function () {
    $server = Server::factory()->create([
        'name' => 'Sigma',
        'ip_address' => '10.0.0.50',
        'ssh_users' => [
            [
                'username' => 'root',
                'encrypted_private_key' => encrypt('root-key'),
            ],
            [
                'username' => 'deploy',
                'encrypted_private_key' => encrypt('deploy-key'),
            ],
        ],
        'ssh_execution_username' => 'root',
    ]);

    $remoteCommandExecutor = \Mockery::mock(RemoteCommandExecutorInterface::class);
    $remoteCommandExecutor->shouldReceive('execute')
        ->once()
        ->withArgs(function (DomainServer $domainServer, string $command): bool {
            return $domainServer->sshExecutionUsername === 'deploy' && $command === 'id -u';
        })
        ->andReturn(new SSHCommandResult(
            command: 'id -u',
            stdout: '1000',
            stderr: '',
            exitStatus: 0,
            duration: 0.10,
        ));

    instance(RemoteCommandExecutorInterface::class, $remoteCommandExecutor);

    artisan('server:exec', [
        'server_id' => $server->id,
        '--user' => 'deploy',
    ])
        ->expectsQuestion('Enter the command to execute:', 'id -u')
        ->expectsOutputToContain('Executing on Sigma (10.0.0.50) as deploy: id -u')
        ->assertSuccessful();
});

it('accepts command flags when provided via cmd option', function () {
    $server = Server::factory()->create([
        'name' => 'Delta',
        'ip_address' => '10.0.0.60',
    ]);

    $remoteCommandExecutor = \Mockery::mock(RemoteCommandExecutorInterface::class);
    $remoteCommandExecutor->shouldReceive('execute')
        ->once()
        ->withArgs(function (DomainServer $domainServer, string $command) use ($server): bool {
            return $domainServer->id === $server->id && $command === 'ls -la';
        })
        ->andReturn(new SSHCommandResult(
            command: 'ls -la',
            stdout: '',
            stderr: '',
            exitStatus: 0,
            duration: 0.05,
        ));

    instance(RemoteCommandExecutorInterface::class, $remoteCommandExecutor);

    artisan('server:exec', [
        'server_id' => $server->id,
        '--cmd' => 'ls -la',
    ])
        ->expectsOutputToContain('Executing on Delta (10.0.0.60) as infoadm: ls -la')
        ->expectsOutputToContain('STDOUT:')
        ->expectsOutputToContain('(empty)')
        ->assertSuccessful();
});

it('normalizes line breaks in cmd option before execution', function () {
    $server = Server::factory()->create([
        'name' => 'Theta',
        'ip_address' => '10.0.0.70',
    ]);

    $remoteCommandExecutor = \Mockery::mock(RemoteCommandExecutorInterface::class);
    $remoteCommandExecutor->shouldReceive('execute')
        ->once()
        ->withArgs(function (DomainServer $domainServer, string $command) use ($server): bool {
            return $domainServer->id === $server->id && $command === 'ls -la';
        })
        ->andReturn(new SSHCommandResult(
            command: 'ls -la',
            stdout: '.\n..',
            stderr: '',
            exitStatus: 0,
            duration: 0.05,
        ));

    instance(RemoteCommandExecutorInterface::class, $remoteCommandExecutor);

    artisan('server:exec', [
        'server_id' => $server->id,
        '--cmd' => "ls\n-la",
    ])
        ->expectsOutputToContain('Executing on Theta (10.0.0.70) as infoadm: ls -la')
        ->assertSuccessful();
});

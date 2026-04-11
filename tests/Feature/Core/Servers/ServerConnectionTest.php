<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Servers;

use App\Core\Servers\Services\ServerConnectionService;
use App\Models\Server;

uses()->group('servers', 'ssh');

it('can test connection to a server', function () {
    // Arrange: Create a test server with fake credentials
    $server = Server::factory()->create([
        'name' => 'Test Server',
        'ip_address' => '127.0.0.1', // Use your actual test server IP
        'ssh_port' => 22,
        'ssh_users' => [
            [
                'username' => 'root',
                'encrypted_private_key' => encrypt('dummy-private-key'),
            ],
        ],
        'ssh_execution_username' => 'root',
        'ssh_username' => 'root',
        // Use a real private key for actual testing
    ]);

    // Act: Test the connection
    $connectionService = app(ServerConnectionService::class);
    $domainServer = $server->toDomainModel();

    // Note: This will fail with fake credentials, but demonstrates the flow
    expect(fn () => $connectionService->testConnection($domainServer))
        ->not->toThrow(\Exception::class);
})->skip('Requires actual SSH server credentials');

it('can convert eloquent model to domain model', function () {
    // Arrange
    $server = Server::factory()->create();

    // Act
    $domainServer = $server->toDomainModel();

    // Assert
    expect($domainServer)
        ->toBeInstanceOf(\App\Core\Servers\Models\Server::class)
        ->and($domainServer->id)->toBe($server->id)
        ->and($domainServer->name)->toBe($server->name)
        ->and($domainServer->ipAddress)->toBe($server->ip_address)
        ->and($domainServer->sshPort)->toBe($server->ssh_port)
        ->and($domainServer->sshExecutionUsername)->toBe($server->ssh_execution_username)
        ->and($domainServer->sshUsers[0]['username'])->toBe($server->ssh_execution_username);
});

it('can create server with factory', function () {
    // Act
    $server = Server::factory()->create();

    // Assert
    expect($server)
        ->toBeInstanceOf(Server::class)
        ->and($server->name)->toBeString()
        ->and($server->ip_address)->toBeString()
        ->and($server->ssh_port)->toBe(22)
        ->and($server->ssh_execution_username)->toBe('infoadm')
        ->and($server->ssh_users)->toBeArray()
        ->and($server->ssh_users[0]['username'])->toBe('infoadm')
        ->and($server->provisioning_engine)->toBe('easyengine')
        ->and($server->is_active)->toBeTrue();
});

it('can create inactive server', function () {
    // Act
    $server = Server::factory()->inactive()->create();

    // Assert
    expect($server->is_active)->toBeFalse();
});

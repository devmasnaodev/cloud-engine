<?php

use App\Core\Servers\Contracts\ServerConnectionServiceInterface;
use App\Core\Servers\Models\Server as DomainServer;
use App\Models\Server;

use function Pest\Laravel\artisan;
use function Pest\Laravel\instance;

it('prompts for an active server when no server id is provided for connection testing', function () {
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

    $connectionService = \Mockery::mock(ServerConnectionServiceInterface::class);
    $connectionService->shouldReceive('testConnection')
        ->once()
        ->withArgs(function (DomainServer $server) use ($alphaServer): bool {
            return $server->id === $alphaServer->id;
        })
        ->andReturn(true);

    instance(ServerConnectionServiceInterface::class, $connectionService);

    artisan('server:test-connection')
        ->expectsChoice('Select a server to test:', $alphaServer->id, [
            $alphaServer->id => $selectedOption,
            $zuluServer->id => $otherOption,
        ])
        ->expectsOutputToContain('Testing connection to: Alpha (10.0.0.10)')
        ->expectsOutputToContain('Connection successful!')
        ->assertSuccessful();

    expect($alphaServer->fresh()->last_connected_at)->not->toBeNull();
});

it('fails when there are no active servers to select for connection testing', function () {
    Server::factory()->inactive()->create();

    artisan('server:test-connection')
        ->expectsOutputToContain('No active servers found in database')
        ->assertFailed();
});

<?php

declare(strict_types=1);

namespace Tests\Feature\Core\Provisioning;

use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Engines\EasyEngine\EasyEngineCommandBuilder;
use App\Core\Engines\EasyEngine\EasyEngineEngine;
use App\Core\Engines\Exceptions\CommandExecutionException;
use App\Core\Engines\Executor\CommandExecutor;
use App\Core\Engines\Executor\CommandNormalizer;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Execution\RemoteCommandOptions;
use App\Core\Servers\Models\Server;
use DateTimeImmutable;

uses()->group('provisioning', 'easyengine');

it('can execute a simple command via executor', function () {
    $engine = app(EasyEngineEngine::class);
    $executor = app(CommandExecutor::class);

    expect($executor)->toBeInstanceOf(CommandExecutor::class)
        ->and($engine)->toBeInstanceOf(EasyEngineEngine::class);
})->skip('Requires actual SSH server');

it('can normalize domain names', function () {
    $normalizer = app(CommandNormalizer::class);

    expect($normalizer->normalizeDomain('example.com'))->toBe('example.com')
        ->and($normalizer->normalizeDomain('https://example.com'))->toBe('example.com')
        ->and($normalizer->normalizeDomain('http://www.example.com/'))->toBe('example.com')
        ->and($normalizer->normalizeDomain('WWW.EXAMPLE.COM'))->toBe('example.com');
});

it('validates invalid domain names', function () {
    $normalizer = app(CommandNormalizer::class);

    $normalizer->normalizeDomain('invalid domain with spaces');
})->throws(CommandExecutionException::class);

it('can normalize PHP versions', function () {
    $normalizer = app(CommandNormalizer::class);

    expect($normalizer->normalizePhpVersion('8.3'))->toBe('8.3')
        ->and($normalizer->normalizePhpVersion('8.2'))->toBe('8.2')
        ->and($normalizer->normalizePhpVersion('8.1'))->toBe('8.1');
});

it('rejects invalid PHP versions', function () {
    $normalizer = app(CommandNormalizer::class);

    $normalizer->normalizePhpVersion('9.0');
})->throws(CommandExecutionException::class);

it('can build EasyEngine commands', function () {
    $builder = app(EasyEngineCommandBuilder::class);

    $listCommand = $builder->buildListSites();
    expect($listCommand)->toBe("bash -l -c 'sudo ee site list --format=json'");

    $createCommand = $builder->buildCreateSite('example.com', [
        'type' => 'wordpress',
        'php_version' => '8.3',
        'ssl' => 'le',
    ]);

    expect($createCommand)->toContain('script -q -c')
        ->and($createCommand)->toContain('ee site create')
        ->and($createCommand)->toContain("'example.com'")
        ->and($createCommand)->toContain('8.3')
        ->and($createCommand)->toContain('le')
        ->and($createCommand)->toContain('--yes');
});

it('uses action-specific timeouts for easyengine commands', function () {
    $capturedTimeouts = [];

    $executor = new class($capturedTimeouts) implements RemoteCommandExecutorInterface
    {
        /**
         * @param  array<string, int|null>  $capturedTimeouts
         */
        public function __construct(private array &$capturedTimeouts) {}

        public function run(Server $server, string $command, ?RemoteCommandOptions $options = null, ?callable $onOutput = null): SSHCommandResult
        {
            if (str_contains($command, 'site list')) {
                $this->capturedTimeouts['list_sites'] = $options?->timeout;
            }

            if (str_contains($command, 'site create')) {
                $this->capturedTimeouts['create_site'] = $options?->timeout;
            }

            return new SSHCommandResult(
                command: $command,
                stdout: '[]',
                stderr: '',
                exitStatus: 0,
                duration: 0.01,
            );
        }

        public function runMultiple(Server $server, array $commands, ?RemoteCommandOptions $options = null): array
        {
            return [];
        }

        public function execute(Server $server, string $command): SSHCommandResult
        {
            return $this->run($server, $command);
        }

        public function executeMultiple(Server $server, array $commands): array
        {
            return [];
        }

        public function testConnection(Server $server): bool
        {
            return true;
        }
    };

    $engine = new EasyEngineEngine($executor, app(EasyEngineCommandBuilder::class));
    $server = fakeServer();

    $engine->runAction($server, 'list_sites');
    $engine->runAction($server, 'create_site', ['domain' => 'example.com']);

    expect($capturedTimeouts)->toBe([
        'list_sites' => 120,
        'create_site' => 900,
    ]);
});

function fakeServer(): Server
{
    return new Server(
        id: 1,
        name: 'Test Server',
        ipAddress: '192.168.1.10',
        sshPort: 22,
        sshUsers: [[
            'username' => 'easyengine',
            'encrypted_private_key' => 'encrypted-key',
        ]],
        sshExecutionUsername: 'easyengine',
        provisioningEngine: 'easyengine',
        isActive: true,
        createdAt: new DateTimeImmutable('2026-03-10 12:00:00'),
    );
}

<?php

declare(strict_types=1);

use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Engines\EasyEngine\EasyEngineCommandBuilder;
use App\Core\Engines\EasyEngine\EasyEngineEngine;
use App\Core\Provisioning\Recipes\Engine\InstallEasyEngineRecipe;
use App\Core\Provisioning\Recipes\Setup\InitialServerSetupRecipe;
use App\Core\Provisioning\Recipes\User\CreateNonRootUserRecipe;
use App\Core\Provisioning\Registry\RecipeRegistry;
use App\Core\Provisioning\Runner\RecipeRunner;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Execution\RemoteCommandOptions;
use App\Core\Servers\Models\Server as DomainServer;
use App\Jobs\RunProvisioningRecipeJob;
use App\Models\ProvisioningRun;
use App\Models\Server;
use App\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

it('declares execution-user metadata for provisioning recipes', function () {
    expect((new InitialServerSetupRecipe)->defaultExecutionUsername())->toBe('root')
        ->and((new InitialServerSetupRecipe)->allowsExecutionUserSelection())->toBeFalse()
        ->and((new CreateNonRootUserRecipe)->defaultExecutionUsername())->toBe('root')
        ->and((new CreateNonRootUserRecipe)->allowsExecutionUserSelection())->toBeFalse()
        ->and((new InstallEasyEngineRecipe)->defaultExecutionUsername())->toBe('root')
        ->and((new InstallEasyEngineRecipe)->allowsExecutionUserSelection())->toBeFalse();
});

it('queues provisioning runs with root as the default execution user', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = provisionableServer();

    $response = $this
        ->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provisioning.run', $server), [
            'recipe_id' => 'initial-server-setup',
        ]);

    $response->assertRedirect(route('servers.show', $server));

    $run = ProvisioningRun::query()->sole();

    expect($run->execution_username)->toBe('root');

    Queue::assertPushed(RunProvisioningRecipeJob::class, 1);
});

it('queues install easyengine runs with root as the default execution user', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = provisionableServer();

    $response = $this
        ->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provisioning.run', $server), [
            'recipe_id' => 'install-easyengine',
        ]);

    $response->assertRedirect(route('servers.show', $server));

    $run = ProvisioningRun::query()->sole();

    expect($run->execution_username)->toBe('root');
});

it('rejects execution-user overrides for root-only recipes', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = provisionableServer();

    $this
        ->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provisioning.run', $server), [
            'recipe_id' => 'create-non-root-user',
            'execution_username' => 'easyengine',
        ])
        ->assertSessionHasErrors('execution_username');

    expect(ProvisioningRun::count())->toBe(0);
});

it('rejects provisioning runs when the required execution user is not configured', function () {
    Queue::fake();

    $user = User::factory()->create();
    $server = Server::factory()->create([
        'ssh_users' => [[
            'username' => 'easyengine',
            'encrypted_private_key' => encrypt(fakePrivateKey()),
        ]],
        'ssh_execution_username' => 'easyengine',
    ]);

    $this
        ->actingAs($user)
        ->from(route('servers.show', $server))
        ->post(route('servers.provisioning.run', $server), [
            'recipe_id' => 'initial-server-setup',
        ])
        ->assertSessionHasErrors('execution_username');

    expect(ProvisioningRun::count())->toBe(0);
});

it('exposes recipe execution-user metadata and active run execution user on the server page', function () {
    $user = User::factory()->create();
    $server = provisionableServer();

    ProvisioningRun::create([
        'server_id' => $server->id,
        'recipe_id' => 'install-easyengine',
        'recipe_name' => 'Install Easy Engine',
        'execution_username' => 'easyengine',
        'status' => 'running',
    ]);

    $engine = new EasyEngineEngine(
        new class implements RemoteCommandExecutorInterface
        {
            public function run(
                DomainServer $server,
                string $command,
                ?RemoteCommandOptions $options = null,
                ?callable $onOutput = null,
            ): SSHCommandResult {
                return new SSHCommandResult(
                    command: $command,
                    stdout: '[]',
                    stderr: '',
                    exitStatus: 0,
                    duration: 0.01,
                );
            }

            public function runMultiple(DomainServer $server, array $commands, ?RemoteCommandOptions $options = null): array
            {
                return [];
            }

            public function execute(DomainServer $server, string $command): SSHCommandResult
            {
                return $this->run($server, $command);
            }

            public function executeMultiple(DomainServer $server, array $commands): array
            {
                return [];
            }

            public function testConnection(DomainServer $server): bool
            {
                return true;
            }
        },
        app(EasyEngineCommandBuilder::class),
    );

    app()->instance(EasyEngineEngine::class, $engine);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/show')
            ->where('activeRun.executionUsername', 'easyengine')
            ->where('recipes', function ($recipes): bool {
                $indexed = collect($recipes)->keyBy('id');

                return $indexed->get('initial-server-setup')['defaultExecutionUsername'] === 'root'
                    && $indexed->get('initial-server-setup')['allowsExecutionUserSelection'] === false
                    && $indexed->get('install-easyengine')['defaultExecutionUsername'] === 'root'
                    && $indexed->get('install-easyengine')['allowsExecutionUserSelection'] === false;
            })
        );
});

it('does not attempt to load sites on the server page when no engine is configured', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'provisioning_engine' => null,
    ]);

    $engine = new EasyEngineEngine(
        new class implements RemoteCommandExecutorInterface
        {
            public function run(
                DomainServer $server,
                string $command,
                ?RemoteCommandOptions $options = null,
                ?callable $onOutput = null,
            ): SSHCommandResult {
                throw new RuntimeException('list_sites should not run without an engine.');
            }

            public function runMultiple(DomainServer $server, array $commands, ?RemoteCommandOptions $options = null): array
            {
                return [];
            }

            public function execute(DomainServer $server, string $command): SSHCommandResult
            {
                return $this->run($server, $command);
            }

            public function executeMultiple(DomainServer $server, array $commands): array
            {
                return [];
            }

            public function testConnection(DomainServer $server): bool
            {
                return true;
            }
        },
        app(EasyEngineCommandBuilder::class),
    );

    app()->instance(EasyEngineEngine::class, $engine);

    $this->actingAs($user)
        ->get(route('servers.show', $server))
        ->assertInertia(fn (Assert $page) => $page
            ->component('servers/show')
            ->where('server.provisioning_engine', null)
            ->where('sites', [])
            ->where('error', null)
        );
});

it('executes provisioning jobs using the persisted execution username', function () {
    $server = provisionableServer();
    $run = ProvisioningRun::create([
        'server_id' => $server->id,
        'recipe_id' => 'install-easyengine',
        'recipe_name' => 'Install Easy Engine',
        'execution_username' => 'easyengine',
        'status' => 'pending',
    ]);

    $executor = new class implements RemoteCommandExecutorInterface
    {
        /** @var string[] */
        public array $executedUsernames = [];

        public function run(
            DomainServer $server,
            string $command,
            ?RemoteCommandOptions $options = null,
            ?callable $onOutput = null,
        ): SSHCommandResult {
            $this->executedUsernames[] = $server->sshExecutionUsername;

            return new SSHCommandResult(
                command: $command,
                stdout: 'ok',
                stderr: '',
                exitStatus: 0,
                duration: 0.01,
            );
        }

        public function runMultiple(DomainServer $server, array $commands, ?RemoteCommandOptions $options = null): array
        {
            return [];
        }

        public function execute(DomainServer $server, string $command): SSHCommandResult
        {
            return $this->run($server, $command);
        }

        public function executeMultiple(DomainServer $server, array $commands): array
        {
            return [];
        }

        public function testConnection(DomainServer $server): bool
        {
            return true;
        }
    };

    $runner = new RecipeRunner($executor, app(Dispatcher::class));
    $registry = new RecipeRegistry;
    $registry->register(new InstallEasyEngineRecipe);

    $job = new RunProvisioningRecipeJob($server->id, 'install-easyengine', $run->id);
    $job->handle($runner, $registry, app(Dispatcher::class));

    expect($executor->executedUsernames)->not->toBeEmpty()
        ->and(array_values(array_unique($executor->executedUsernames)))->toBe(['easyengine'])
        ->and($run->fresh()->status)->toBe('completed');
});

function provisionableServer(): Server
{
    return Server::factory()->create([
        'ssh_users' => [
            [
                'username' => 'root',
                'encrypted_private_key' => encrypt(fakePrivateKey()),
            ],
            [
                'username' => 'easyengine',
                'encrypted_private_key' => encrypt(fakePrivateKey()),
            ],
        ],
        'ssh_execution_username' => 'easyengine',
    ]);
}

function fakePrivateKey(): string
{
    return <<<'KEY'
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACDlFFh0jOwlvHk53jsBByg73uUEDZmWYkVZ0WNMMEo4QgAAAJjyFdeQ8hXX
kAAAAAtzc2gtZWQyNTUxOQAAACDlFFh0jOwlvHk53jsBByg73uUEDZmWYkVZ0WNMMEo4Qg
AAAECsfO7j0KWpcYy1vbQ3BNJQaoqD/n4vP1BVgUfcKF3rY+UUWHSM7CW8eTneOwEHKDve
5QQNmZZiRVnRY0wwSjhCAAAADmdpdGh1Yi1hY3Rpb25zAQIDBAUGBw==
-----END OPENSSH PRIVATE KEY-----
KEY;
}

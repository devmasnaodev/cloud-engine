<?php

declare(strict_types=1);

use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Provisioning\Recipes\Setup\InitialServerSetupRecipe;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Execution\RemoteCommandOptions;
use App\Core\Servers\Models\Server;
use App\Core\Servers\Services\ServerInfoDetector;
use App\Core\Shell\Bash\Commands\InstallPackages;

it('detects remote server info through the server info detector', function () {
    $executor = new class implements RemoteCommandExecutorInterface
    {
        public function run(Server $server, string $command, ?RemoteCommandOptions $options = null, ?callable $onOutput = null): SSHCommandResult
        {
            return $this->execute($server, $command);
        }

        public function runMultiple(Server $server, array $commands, ?RemoteCommandOptions $options = null): array
        {
            return [];
        }

        public function execute(Server $server, string $command): SSHCommandResult
        {
            return new SSHCommandResult(
                command: $command,
                stdout: "ID=ubuntu\nPRETTY_NAME=\"Ubuntu 24.04 LTS\"\nVERSION_ID=\"24.04\"\n",
                stderr: '',
                exitStatus: 0,
                duration: 0.01,
            );
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

    $detector = new ServerInfoDetector($executor);

    expect($detector->detect(fakeServer()))->toBe([
        'id' => 'ubuntu',
        'pretty_name' => 'Ubuntu 24.04 LTS',
        'version_id' => '24.04',
    ]);
});

it('returns empty server info when remote detection fails', function () {
    $executor = new class implements RemoteCommandExecutorInterface
    {
        public function run(Server $server, string $command, ?RemoteCommandOptions $options = null, ?callable $onOutput = null): SSHCommandResult
        {
            return $this->execute($server, $command);
        }

        public function runMultiple(Server $server, array $commands, ?RemoteCommandOptions $options = null): array
        {
            return [];
        }

        public function execute(Server $server, string $command): SSHCommandResult
        {
            throw new RuntimeException('SSH error');
        }

        public function executeMultiple(Server $server, array $commands): array
        {
            return [];
        }

        public function testConnection(Server $server): bool
        {
            return false;
        }
    };

    $detector = new ServerInfoDetector($executor);

    expect($detector->detect(fakeServer()))->toBe([
        'id' => null,
        'pretty_name' => null,
        'version_id' => null,
    ]);
});

it('includes package installation after package upgrade in the initial setup recipe', function () {
    $recipe = new InitialServerSetupRecipe;
    $steps = $recipe->steps();
    $stepIds = array_map(static fn ($step): string => $step->id(), $steps);

    expect($stepIds)->toContain('install-packages')
        ->and(array_search('install-packages', $stepIds, true))->toBeGreaterThan(array_search('upgrade-packages', $stepIds, true));
});

it('uses the expected default package list in the install packages command', function () {
    $command = new InstallPackages;

    expect($command->getPackages())->toBe(['curl', 'git', 'unzip', 'fail2ban', 'ufw'])
        ->and($command->command())->toContain("'curl'")
        ->and($command->command())->toContain("'fail2ban'")
        ->and($command->command())->toContain("'ufw'");
});

function fakeServer(): Server
{
    return new Server(
        id: 1,
        name: 'Test Server',
        ipAddress: '192.168.1.10',
        sshPort: 22,
        sshUsers: [[
            'username' => 'root',
            'encrypted_private_key' => 'encrypted-key',
        ]],
        sshExecutionUsername: 'root',
        provisioningEngine: 'easyengine',
        isActive: true,
        createdAt: new DateTimeImmutable('2026-03-10 12:00:00'),
    );
}

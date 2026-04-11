<?php

declare(strict_types=1);

namespace App\Core\Servers\Contracts;

use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Servers\Execution\RemoteCommandOptions;
use App\Core\Servers\Models\Server;

interface RemoteCommandExecutorInterface
{
    /**
     * @param  callable|null  $onOutput  fn(string $chunk): void — called with each stdout chunk during execution
     */
    public function run(Server $server, string $command, ?RemoteCommandOptions $options = null, ?callable $onOutput = null): SSHCommandResult;

    /**
     * @param  array<int, string>  $commands
     * @return array<int, SSHCommandResult>
     */
    public function runMultiple(Server $server, array $commands, ?RemoteCommandOptions $options = null): array;

    public function execute(Server $server, string $command): SSHCommandResult;

    /**
     * @param  array<int, string>  $commands
     * @return array<int, SSHCommandResult>
     */
    public function executeMultiple(Server $server, array $commands): array;

    public function testConnection(Server $server): bool;
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\ServerPrompt;
use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Models\Server;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

final class ExecuteRemoteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'server:exec {server_id? : ID of the server} {--cmd= : Command to execute (use quotes for commands with flags, e.g. --cmd="ls -la")} {--user= : SSH user used to execute the command}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute a command on a remote server via SSH';

    /**
     * Execute the console command.
     */
    public function handle(RemoteCommandExecutorInterface $remoteCommandExecutor, ServerPrompt $serverPrompt): int
    {
        $serverId = $this->argument('server_id');

        if ($serverId === null) {
            $server = $serverPrompt->selectActiveServer();

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

        $baseDomainServer = $server->toDomainModel();
        $forcedUsername = $this->option('user');
        $executionUsername = $serverPrompt->resolveSshExecutionUsername(
            server: $baseDomainServer,
            forcedUsername: is_string($forcedUsername) ? $forcedUsername : null,
        );

        if ($executionUsername === null) {
            error('No SSH users are configured for this server. Add at least one SSH user before executing commands.');

            return self::FAILURE;
        }

        $domainServer = $serverPrompt->withExecutionUsername($baseDomainServer, $executionUsername);

        $optionCommand = $this->option('cmd');

        $rawCommand = is_string($optionCommand) && $optionCommand !== ''
            ? $optionCommand
            : text(
                label: 'Enter the command to execute:',
                placeholder: 'ls -la',
                required: true
            );

        $command = $this->normalizeCommandInput($rawCommand);

        if ($command === '') {
            error('The command cannot be empty.');

            return self::FAILURE;
        }

        info("Executing on {$server->name} ({$server->ip_address}) as {$executionUsername}: {$command}");

        try {
            // Execute command
            $startTime = microtime(true);
            $result = $remoteCommandExecutor->execute($domainServer, $command);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Debug output
            $this->newLine();
            $this->line('<fg=yellow>DEBUG INFO:</>');
            $this->line("Command sent: {$command}");
            $this->line("Exit status: {$result->exitStatus}");
            $this->line('STDOUT length: '.strlen($result->stdout));
            $this->line('STDERR length: '.strlen($result->stderr));
            $this->line('Has errors: '.($result->hasErrors() ? 'YES' : 'NO'));
            $this->line('Is successful: '.($result->isSuccessful() ? 'YES' : 'NO'));

            // Display results
            $this->newLine();
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->line("Exit Status: {$result->exitStatus}");
            $this->line("Duration: {$duration}ms");
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            $this->newLine();
            $this->line('<fg=green>STDOUT:</>');

            $stdout = rtrim($result->stdout, "\r\n");
            $this->line($stdout !== '' ? $stdout : '<fg=gray>(empty)</>');

            if (! empty($result->stderr)) {
                $this->newLine();
                $this->line('<fg=red>STDERR:</>');
                $this->line($result->stderr);
            }

            // Update last connected timestamp
            $server->update(['last_connected_at' => now()]);

            return $result->exitStatus === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            error('Failed to execute command: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function normalizeCommandInput(string $command): string
    {
        $singleLineCommand = str_replace(["\r", "\n", "\t"], ' ', $command);

        return trim(preg_replace('/ {2,}/', ' ', $singleLineCommand) ?? '');
    }
}

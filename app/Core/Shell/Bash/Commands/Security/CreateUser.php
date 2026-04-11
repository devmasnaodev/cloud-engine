<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Security;

use App\Core\Commands\AbstractCommand;

/**
 * Creates a new non-root system user with a home directory, bash shell
 * and adds them to the sudo group.
 */
final class CreateUser extends AbstractCommand
{
    public function __construct(private readonly string $username) {}

    public function id(): string
    {
        return 'create-user';
    }

    public function name(): string
    {
        return 'Create user and add to sudo group';
    }

    public function description(): string
    {
        return 'Add a regular user with a home directory, bash shell, and add to sudo group';
    }

    public function command(): string
    {
        $u = escapeshellarg($this->username);

        return 'bash -lc '.escapeshellarg("useradd --create-home --shell /bin/bash --groups sudo {$u}");
    }

    public function validateResult(object $result): ?string
    {
        $stdout = mb_strtolower((string) ($result->stdout ?? ''));
        $stderr = mb_strtolower((string) ($result->stderr ?? ''));

        $exit = property_exists($result, 'exitStatus')
            ? (int) $result->exitStatus
            : (property_exists($result, 'exit') ? (int) $result->exit : null);

        if ($exit !== null && $exit !== 0) {
            if (str_contains($stderr, 'already exists') || str_contains($stdout, 'already exists')) {
                return 'STOP: user '.$this->username.' already exists';
            }

            $msg = sprintf('useradd failed with exit status %d', $exit);
            $stderrTrimmed = trim((string) ($result->stderr ?? ''));

            if ($stderrTrimmed !== '') {
                $msg .= ': '.$stderrTrimmed;
            }

            return $msg;
        }

        return null;
    }
}

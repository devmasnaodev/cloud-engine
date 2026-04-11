<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\EasyEngine;

use App\Core\Commands\AbstractCommand;

/**
 * Creates a sudoers fragment granting the given user passwordless access
 * to `ee` and `docker`, then validates the sudoers syntax with `visudo -c`.
 */
final class EasyEngineSudoers extends AbstractCommand
{
    public function __construct(private readonly string $username) {}

    public function id(): string
    {
        return 'ee-sudoers';
    }

    public function name(): string
    {
        return 'Configure sudoers for Easy Engine';
    }

    public function description(): string
    {
        return 'Create /etc/sudoers.d/10-easyengine allowing passwordless ee and docker';
    }

    public function command(): string
    {
        $line = $this->username.' ALL= NOPASSWD: /usr/local/bin/ee, /usr/bin/docker';

        $script = "printf '%s\n' ".escapeshellarg($line)
            .' > /etc/sudoers.d/10-easyengine'
            .' && chmod 440 /etc/sudoers.d/10-easyengine'
            .' && visudo -c -f /etc/sudoers.d/10-easyengine';

        return 'bash -lc '.escapeshellarg($script);
    }

    public function validateResult(object $result): ?string
    {
        $exit = property_exists($result, 'exitStatus')
            ? (int) $result->exitStatus
            : (property_exists($result, 'exit') ? (int) $result->exit : null);

        if ($exit !== null && $exit !== 0) {
            return 'Failed to write or validate sudoers fragment for easyengine';
        }

        return null;
    }
}

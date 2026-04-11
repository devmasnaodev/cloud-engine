<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\EasyEngine;

use App\Core\Commands\AbstractCommand;

/**
 * Adds the given user to the `docker` group so they can run
 * Docker commands without sudo.
 */
final class AddDockerGroup extends AbstractCommand
{
    public function __construct(private readonly string $username) {}

    public function id(): string
    {
        return 'add-docker-group';
    }

    public function name(): string
    {
        return 'Add user to docker group';
    }

    public function description(): string
    {
        return 'Add the user to the docker group to allow running docker without sudo';
    }

    public function command(): string
    {
        $u = escapeshellarg($this->username);

        return 'bash -lc '.escapeshellarg("usermod -aG docker {$u} || true");
    }

    public function validateResult(object $result): ?string
    {
        $exit = property_exists($result, 'exitStatus')
            ? (int) $result->exitStatus
            : (property_exists($result, 'exit') ? (int) $result->exit : null);

        if ($exit !== null && $exit !== 0) {
            return 'Failed to add user to docker group';
        }

        return null;
    }
}

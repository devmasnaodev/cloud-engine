<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\EasyEngine;

use App\Core\Commands\AbstractCommand;

/**
 * Validates that Easy Engine was installed correctly by running `ee cli version`.
 */
final class EasyEngineValidate extends AbstractCommand
{
    public function id(): string
    {
        return 'ee-validate';
    }

    public function name(): string
    {
        return 'Validate Easy Engine installation';
    }

    public function description(): string
    {
        return 'Run `ee cli version` to validate the installation';
    }

    public function command(): string
    {
        return 'bash -lc '.escapeshellarg('ee cli version');
    }

    public function validateResult(object $result): ?string
    {
        $exit = property_exists($result, 'exitStatus')
            ? (int) $result->exitStatus
            : (property_exists($result, 'exit') ? (int) $result->exit : null);

        if ($exit !== null && $exit !== 0) {
            return 'Easy Engine binary not responding to `ee cli version`';
        }

        return null;
    }
}

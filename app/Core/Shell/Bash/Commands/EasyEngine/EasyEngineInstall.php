<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\EasyEngine;

use App\Core\Commands\AbstractCommand;

/**
 * Runs the official Easy Engine one-liner installer.
 * Grants up to 30 minutes for the installation to complete.
 */
final class EasyEngineInstall extends AbstractCommand
{
    public function id(): string
    {
        return 'ee-install';
    }

    public function name(): string
    {
        return 'Install Easy Engine';
    }

    public function description(): string
    {
        return 'Run official Easy Engine installer (wget rt.cx/ee4 && sudo bash ee)';
    }

    public function command(): string
    {
        $script = 'wget -qO ee https://rt.cx/ee4 && sudo bash ee';

        return 'bash -lc '.escapeshellarg($script);
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), ['timeout' => 1800]);
    }

    public function validateResult(object $result): ?string
    {
        $exit = property_exists($result, 'exitStatus')
            ? (int) $result->exitStatus
            : (property_exists($result, 'exit') ? (int) $result->exit : null);

        if ($exit !== null && $exit !== 0) {
            return sprintf('Easy Engine installer failed with exit status %d', $exit);
        }

        return null;
    }
}

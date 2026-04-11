<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Concerns;

/**
 * Provides a reusable bash preamble that makes apt operations resilient to
 * lock contention on fresh Ubuntu/Debian servers.
 *
 * On first boot, systemd timers like apt-daily.service and
 * unattended-upgrades.service frequently hold /var/lib/dpkg/lock-frontend,
 * causing any immediate apt-get call to exit with status 100.
 *
 * Strategy:
 *  1. Stop the known background apt services so they release the lock.
 *  2. Rely on apt's built-in `-o DPkg::lock::timeout` to wait up to $seconds
 *     for any remaining holder to finish (available in apt >= 1.9.11,
 *     which ships with Ubuntu 22.04+).
 */
trait WaitsForAptLock
{
    /**
     * Returns a bash snippet that stops background apt services and configures
     * the apt lock timeout. Embed this at the top of every apt-get command.
     *
     * @param  int  $timeoutSeconds  Maximum seconds to wait for the dpkg lock (default: 300)
     */
    protected function aptPreamble(int $timeoutSeconds = 300): string
    {
        return implode("\n", [
            '# Stop background apt services that may hold the dpkg lock',
            'systemctl stop unattended-upgrades 2>/dev/null || true',
            'systemctl stop apt-daily.service 2>/dev/null || true',
            'systemctl stop apt-daily-upgrade.service 2>/dev/null || true',
            'systemctl stop apt-daily.timer 2>/dev/null || true',
            'systemctl stop apt-daily-upgrade.timer 2>/dev/null || true',
            '',
            '# Wait for any remaining dpkg lock holders',
            'while flock --timeout 1 /var/lib/dpkg/lock-frontend --command true 2>/dev/null; [ $? -ne 0 ]; do',
            '    echo "Waiting for dpkg lock..."',
            '    sleep 3',
            'done',
            '',
            '# Set apt lock timeout for this session (apt >= 1.9.11 / Ubuntu 22.04+)',
            "export APT_OPTS=\"-o DPkg::lock::timeout={$timeoutSeconds}\"",
        ]);
    }

    /**
     * Wraps an apt-get invocation with the lock preamble inside a bash -lc call.
     *
     * @param  string  $aptCommand  The apt-get ... command (without DEBIAN_FRONTEND prefix)
     */
    protected function aptCommand(string $aptCommand): string
    {
        $script = implode("\n", [
            'set -euo pipefail',
            'export DEBIAN_FRONTEND=noninteractive',
            $this->aptPreamble(),
            '',
            $aptCommand,
        ]);

        return 'bash -lc '.escapeshellarg($script);
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Config;

use App\Core\Commands\AbstractCommand;

/**
 * Displays the running status of SSH, ufw and fail2ban.
 * Used as the last informational step before a reboot.
 */
final class FinalChecks extends AbstractCommand
{
    public function id(): string
    {
        return 'final-checks';
    }

    public function name(): string
    {
        return 'Final checks';
    }

    public function description(): string
    {
        return 'Show basic status for SSH, ufw and fail2ban';
    }

    public function command(): string
    {
        return 'systemctl status ssh --no-pager || true; ufw status verbose || true; systemctl status fail2ban --no-pager || true';
    }
}

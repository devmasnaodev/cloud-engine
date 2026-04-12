<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Config;

use App\Core\Commands\AbstractCommand;

final class Fail2ban extends AbstractCommand
{
    private const TIMEOUT_SECONDS = 180;

    public function id(): string
    {
        return 'fail2ban';
    }

    public function name(): string
    {
        return 'Enable Fail2Ban';
    }

    public function description(): string
    {
        return 'Ensure fail2ban is enabled and running';
    }

    public function command(): string
    {
        return 'systemctl enable --now fail2ban';
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), ['timeout' => self::TIMEOUT_SECONDS]);
    }
}

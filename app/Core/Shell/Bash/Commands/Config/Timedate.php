<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Config;

use App\Core\Commands\AbstractCommand;

final class Timedate extends AbstractCommand
{
    private string $timezone = 'America/Sao_Paulo';

    public function __construct(string $timezone)
    {
        $this->timezone = $timezone;
    }

    public function id(): string
    {
        return 'timezone';
    }

    public function name(): string
    {
        return 'Set Timezone';
    }

    public function description(): string
    {
        return 'Configure timezone with timedatectl';
    }

    public function command(): string
    {
        return 'timedatectl set-timezone '.escapeshellarg($this->timezone);
    }

    public function setTimezone(string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }
}

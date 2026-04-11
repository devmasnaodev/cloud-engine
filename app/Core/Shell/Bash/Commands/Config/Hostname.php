<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Config;

use App\Core\Commands\AbstractCommand;

final class Hostname extends AbstractCommand
{
    private string $hostname = 'ee-setup-server';

    public function __construct(string $hostname)
    {
        $this->hostname = $hostname;
    }

    public function id(): string
    {
        return 'hostname';
    }

    public function name(): string
    {
        return 'Set Hostname';
    }

    public function description(): string
    {
        return 'Set system hostname using hostnamectl';
    }

    public function command(): string
    {
        return 'hostnamectl set-hostname '.escapeshellarg($this->hostname);
    }

    public function setHostname(string $hostname): void
    {
        $this->hostname = $hostname;
    }

    public function getHostname(): string
    {
        return $this->hostname;
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands;

use App\Core\Commands\AbstractCommand;
use App\Core\Shell\Bash\Concerns\WaitsForAptLock;

final class UpgradePackages extends AbstractCommand
{
    use WaitsForAptLock;

    public function id(): string
    {
        return 'apt-update-upgrade';
    }

    public function name(): string
    {
        return 'Update & Upgrade Packages';
    }

    public function description(): string
    {
        return 'Refresh apt cache and perform a non-interactive upgrade';
    }

    public function command(): string
    {
        return $this->aptCommand(
            'apt-get $APT_OPTS update'
            .' && apt-get $APT_OPTS -y dist-upgrade'
            .' && apt-get $APT_OPTS -y autoremove',
        );
    }

    public function env(): array
    {
        return ['DEBIAN_FRONTEND' => 'noninteractive'];
    }
}

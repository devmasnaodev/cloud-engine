<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Recipes\Setup;

use App\Core\Commands\CommandInterface;
use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Shell\Bash\Commands\Common\Reboot;
use App\Core\Shell\Bash\Commands\Config\Fail2ban;
use App\Core\Shell\Bash\Commands\Config\FinalChecks;
use App\Core\Shell\Bash\Commands\Config\Hostname;
use App\Core\Shell\Bash\Commands\Config\Timedate;
use App\Core\Shell\Bash\Commands\Config\Ufw;
use App\Core\Shell\Bash\Commands\InstallPackages;
use App\Core\Shell\Bash\Commands\UpgradePackages;

/**
 * Performs an initial hardening and configuration of a fresh Debian/Ubuntu server.
 *
 * Steps: set hostname → timezone → upgrade packages → install base packages
 *        → configure ufw → configure fail2ban → final status check → reboot
 */
final class InitialServerSetupRecipe implements ProvisioningRecipeInterface
{
    public function __construct(
        private readonly string $hostname = 'ee-setup-server',
        private readonly string $timezone = 'America/Sao_Paulo',
    ) {}

    public function id(): string
    {
        return 'initial-server-setup';
    }

    public function name(): string
    {
        return 'Initial Server Setup';
    }

    public function description(): string
    {
        return 'Harden and configure a fresh Debian/Ubuntu server: hostname, timezone, packages, ufw, fail2ban and reboot.';
    }

    public function defaultExecutionUsername(): string
    {
        return 'root';
    }

    public function allowsExecutionUserSelection(): bool
    {
        return false;
    }

    /**
     * @return CommandInterface[]
     */
    public function steps(): array
    {
        return [
            new Hostname($this->hostname),
            new Timedate($this->timezone),
            new UpgradePackages,
            new InstallPackages,
            new Ufw,
            new Fail2ban,
            new FinalChecks,
            new Reboot,
        ];
    }
}

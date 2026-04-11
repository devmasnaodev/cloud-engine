<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands;

use App\Core\Commands\AbstractCommand;
use App\Core\Shell\Bash\Concerns\WaitsForAptLock;

final class InstallPackages extends AbstractCommand
{
    use WaitsForAptLock;

    /** @var array<int,string> */
    private array $packages;

    /**
     * Provide an array of package names to install. If null, defaults are used.
     *
     * @param  array<int,string>|null  $packages
     */
    public function __construct(?array $packages = null)
    {
        $this->packages = $packages ?? $this->defaultPackages();
    }

    private function defaultPackages(): array
    {
        return ['curl', 'git', 'unzip', 'fail2ban', 'ufw'];
    }

    public function id(): string
    {
        return 'install-packages';
    }

    public function name(): string
    {
        return 'Install essential packages';
    }

    public function description(): string
    {
        return 'Install essential packages';
    }

    public function command(): string
    {
        $pkgList = implode(' ', array_map('escapeshellarg', $this->packages));

        return $this->aptCommand("apt-get \$APT_OPTS -y install {$pkgList}");
    }

    /** @return array<string,string> */
    public function env(): array
    {
        return ['DEBIAN_FRONTEND' => 'noninteractive'];
    }

    /** Allow users to override packages after construction if desired. */
    public function setPackages(array $packages): void
    {
        $this->packages = $packages;
    }

    /** @return array<int,string> */
    public function getPackages(): array
    {
        return $this->packages;
    }
}

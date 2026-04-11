<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Security;

use App\Core\Commands\AbstractCommand;

/**
 * Sets PermitRootLogin to prohibit-password in sshd_config
 * and restarts sshd if the configuration test passes.
 */
final class DisableRootSshPassword extends AbstractCommand
{
    public function id(): string
    {
        return 'disable-root-ssh-password';
    }

    public function name(): string
    {
        return 'Disable root SSH login with password';
    }

    public function description(): string
    {
        return 'Set PermitRootLogin to prohibit-password and restart sshd if config test passes';
    }

    public function command(): string
    {
        return "sed --in-place 's/^PermitRootLogin.*/PermitRootLogin prohibit-password/g' /etc/ssh/sshd_config"
            .' && if sshd -t -q; then'
            .' systemctl restart ssh || systemctl restart sshd || service ssh restart || service sshd restart || true;'
            .' fi';
    }
}

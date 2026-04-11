<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Security;

use App\Core\Commands\AbstractCommand;

/**
 * Creates the ~/.ssh directory for a user, optionally copies authorized_keys
 * from root, appends any additional provided public keys and fixes permissions.
 */
final class SshKeysSetup extends AbstractCommand
{
    /**
     * @param  string[]  $otherPublicKeys
     */
    public function __construct(
        private readonly string $username,
        private readonly bool $copyAuthorizedKeysFromRoot = true,
        private readonly array $otherPublicKeys = [],
    ) {}

    public function id(): string
    {
        return 'ssh-keys-setup';
    }

    public function name(): string
    {
        return 'Setup SSH authorized_keys for the user';
    }

    public function description(): string
    {
        return 'Create .ssh, copy/add keys, set ownership and permissions';
    }

    public function command(): string
    {
        $home = '$(eval echo ~'.$this->username.')';

        $cmds = [];
        $cmds[] = "home_directory=\"{$home}\"";
        $cmds[] = 'mkdir --parents "${home_directory}/.ssh"';

        if ($this->copyAuthorizedKeysFromRoot) {
            $cmds[] = 'if [ -f /root/.ssh/authorized_keys ]; then cp /root/.ssh/authorized_keys "${home_directory}/.ssh"; fi';
        }

        foreach ($this->otherPublicKeys as $key) {
            $k = str_replace("'", "'\\''", $key);
            $cmds[] = 'echo \''.$k.'\' >> "${home_directory}/.ssh/authorized_keys"';
        }

        $cmds[] = 'chmod 0700 "${home_directory}/.ssh"';
        $cmds[] = 'chmod 0600 "${home_directory}/.ssh/authorized_keys" || true';
        $cmds[] = 'chown --recursive '.$this->username.':'.$this->username.' "${home_directory}/.ssh" || true';

        return 'bash -lc '.escapeshellarg("set -euo pipefail\n".implode("\n", $cmds));
    }
}

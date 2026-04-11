<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Recipes\User;

use App\Core\Commands\CommandInterface;
use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Shell\Bash\Commands\Config\Ufw;
use App\Core\Shell\Bash\Commands\Security\CreateUser;
use App\Core\Shell\Bash\Commands\Security\DisableRootSshPassword;
use App\Core\Shell\Bash\Commands\Security\SshKeysSetup;

/**
 * Creates a non-root system user, sets up their SSH keys,
 * disables root SSH password login and ensures the firewall allows OpenSSH.
 */
final class CreateNonRootUserRecipe implements ProvisioningRecipeInterface
{
    /**
     * @param  string[]  $otherPublicKeys  Additional public keys to add to authorized_keys
     */
    public function __construct(
        private readonly string $username = 'easyengine',
        private readonly bool $copyAuthorizedKeysFromRoot = true,
        private readonly array $otherPublicKeys = [],
    ) {}

    public function id(): string
    {
        return 'create-non-root-user';
    }

    public function name(): string
    {
        return 'Create Non-Root User';
    }

    public function description(): string
    {
        return 'Create a sudo-enabled user, configure SSH keys, disable root password login and allow OpenSSH through the firewall.';
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
            new CreateUser($this->username),
            new SshKeysSetup($this->username, $this->copyAuthorizedKeysFromRoot, $this->otherPublicKeys),
            new DisableRootSshPassword,
            new Ufw(['OpenSSH']),
        ];
    }
}

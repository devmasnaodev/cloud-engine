<?php

declare(strict_types=1);

namespace App\Core\Provisioning\Recipes\Engine;

use App\Core\Commands\CommandInterface;
use App\Core\Provisioning\Contracts\ProvisioningRecipeInterface;
use App\Core\Shell\Bash\Commands\EasyEngine\AddDockerGroup;
use App\Core\Shell\Bash\Commands\EasyEngine\EasyEngineAliasAutocomplete;
use App\Core\Shell\Bash\Commands\EasyEngine\EasyEngineInstall;
use App\Core\Shell\Bash\Commands\EasyEngine\EasyEngineSudoers;
use App\Core\Shell\Bash\Commands\EasyEngine\EasyEngineValidate;

/**
 * Installs Easy Engine on the remote server for the given user account.
 *
 * Steps: run official installer → validate binary → configure sudoers
 *        → add user to docker group → install alias and autocomplete
 */
final class InstallEasyEngineRecipe implements ProvisioningRecipeInterface
{
    public function __construct(
        private readonly string $username = 'easyengine',
    ) {}

    public function id(): string
    {
        return 'install-easyengine';
    }

    public function name(): string
    {
        return 'Install Easy Engine';
    }

    public function description(): string
    {
        return 'Install Easy Engine via the official installer, configure sudoers and shell integration for the given user.';
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
            new EasyEngineInstall,
            new EasyEngineValidate,
            new EasyEngineSudoers($this->username),
            new AddDockerGroup($this->username),
            new EasyEngineAliasAutocomplete($this->username),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\EasyEngine;

use App\Core\Commands\AbstractCommand;

/**
 * Installs the `ee` shell alias and autocomplete scripts for both bash and zsh
 * inside the target user's home directory.
 */
final class EasyEngineAliasAutocomplete extends AbstractCommand
{
    public function __construct(private readonly string $username) {}

    public function id(): string
    {
        return 'ee-alias-autocomplete';
    }

    public function name(): string
    {
        return 'Add ee alias and install autocomplete for user';
    }

    public function description(): string
    {
        return 'Append alias to ~/.bashrc and ~/.zshrc, install bash and zsh completions';
    }

    public function command(): string
    {
        $home = '$(eval echo ~'.$this->username.')';

        $cmds = [];
        $cmds[] = "home_directory=\"{$home}\"";

        // Bash completion
        $cmds[] = 'wget -qO "${home_directory}/.ee-completion.bash" https://raw.githubusercontent.com/EasyEngine/easyengine/master/utils/ee-completion.bash || true';
        $cmds[] = 'if ! grep -qxF "alias ee=\'sudo ee\'" "${home_directory}/.bashrc" 2>/dev/null; then'
            .' echo "# Easy Engine alias" >> "${home_directory}/.bashrc";'
            .' echo "alias ee=\'sudo ee\'" >> "${home_directory}/.bashrc";'
            .' fi';
        $cmds[] = 'if ! grep -qxF "[ -f ~/.ee-completion.bash ] && . ~/.ee-completion.bash" "${home_directory}/.bashrc" 2>/dev/null; then'
            .' echo "# Load EasyEngine autocomplete" >> "${home_directory}/.bashrc";'
            .' echo "if [ -f ~/.ee-completion.bash ]; then" >> "${home_directory}/.bashrc";'
            .' echo "    . ~/.ee-completion.bash" >> "${home_directory}/.bashrc";'
            .' echo "fi" >> "${home_directory}/.bashrc";'
            .' fi';

        // Zsh completion
        $cmds[] = 'curl -s https://raw.githubusercontent.com/EasyEngine/easyengine/develop/utils/ee-completion.zsh | sudo tee /usr/share/zsh/vendor-completions/_ee >/dev/null || true';
        $cmds[] = 'if ! grep -qxF "# Easy Engine alias" "${home_directory}/.zshrc" 2>/dev/null; then'
            .' echo "# Easy Engine alias" >> "${home_directory}/.zshrc";'
            .' echo "alias ee=\'sudo ee\'" >> "${home_directory}/.zshrc";'
            .' fi';
        $cmds[] = 'if ! grep -qxF "autoload -Uz compinit && compinit" "${home_directory}/.zshrc" 2>/dev/null; then'
            .' echo "autoload -Uz compinit && compinit" >> "${home_directory}/.zshrc";'
            .' fi';

        // Ensure ownership
        $cmds[] = 'chown -R '.$this->username.':'.$this->username.' "${home_directory}" || true';

        return 'bash -lc '.escapeshellarg("set -euo pipefail\n".implode("\n", $cmds));
    }
}

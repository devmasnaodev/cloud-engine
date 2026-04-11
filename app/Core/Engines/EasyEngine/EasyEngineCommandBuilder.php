<?php

declare(strict_types=1);

namespace App\Core\Engines\EasyEngine;

/**
 * Command builder for EasyEngine CLI commands.
 *
 * Builds safe, properly escaped shell commands for EasyEngine operations.
 * All user input must be escaped using escapeshellarg() to prevent injection.
 *
 * Two wrappers are used depending on the command type:
 *
 * - Read-only commands (list, info): wrapped with `bash -l -c '...'` so that
 *   the login environment (PATH, Docker aliases, etc.) is loaded. These commands
 *   return structured output (JSON) and must not have extra TTY noise.
 *
 * - State-changing commands (create, delete, clean, enable, disable, update):
 *   wrapped with `bash -l -c 'script -q -c "..." /dev/null'`. The `script`
 *   utility allocates a pseudo-TTY in the remote shell, which is required because
 *   EasyEngine internally calls `docker exec` to interact with running containers
 *   (e.g. Redis FLUSHALL, nginx reload). Without a PTY those docker exec calls
 *   complete without propagating the operation inside the container. phpseclib's
 *   enablePTY() cannot be used here because it is incompatible with exec()
 *   callbacks (streaming output), so `script` is the correct layer for this.
 */
final class EasyEngineCommandBuilder
{
    /**
     * Build command to list all sites.
     */
    public function buildListSites(): string
    {
        return $this->withLoginShell('sudo ee site list --format=json');
    }

    /**
     * Build command to create a new site.
     *
     * @param  string  $domain  The domain name for the site
     * @param  array<string, mixed>  $options  Additional options for site creation
     */
    public function buildCreateSite(string $domain, array $options = []): string
    {
        $command = 'sudo ee site create '.escapeshellarg($domain);
        $requestedType = isset($options['type']) ? (string) $options['type'] : null;
        $hasWordPressOptions = isset($options['title'])
            || isset($options['locale'])
            || isset($options['mu'])
            || isset($options['admin_user'])
            || isset($options['admin_pass'])
            || isset($options['admin_email'])
            || isset($options['skip_install'])
            || isset($options['skip_content']);

        $effectiveType = 'html';

        if ($requestedType !== null) {
            $effectiveType = match ($requestedType) {
                'wordpress', 'wp' => 'wp',
                'php' => 'php',
                default => 'html',
            };

            $command .= match ($effectiveType) {
                'wp' => ' --type=wp',
                'php' => ' --type=php',
                default => ' --type=html',
            };
        } elseif ($hasWordPressOptions) {
            $effectiveType = 'wp';
            $command .= ' --type=wp';
        }

        if (isset($options['php_version']) && $options['php_version'] !== '') {
            $command .= ' --php='.escapeshellarg((string) $options['php_version']);
        }

        if (isset($options['title']) && $options['title'] !== '') {
            $command .= ' --title='.escapeshellarg((string) $options['title']);
        }

        if (isset($options['public_dir']) && $options['public_dir'] !== '') {
            $command .= ' --public-dir='.escapeshellarg((string) $options['public_dir']);
        }

        if (isset($options['cache']) && $options['cache'] === true) {
            $command .= ' --cache';
        }

        if (isset($options['ssl']) && $options['ssl'] !== '' && $options['ssl'] !== 'none') {
            $command .= ' --ssl='.escapeshellarg((string) $options['ssl']);
        }

        if (isset($options['wildcard']) && $options['wildcard'] === true) {
            $command .= ' --wildcard';
        }

        if (isset($options['mu']) && in_array($options['mu'], ['subdir', 'subdom'], true)) {
            $command .= ' --mu='.escapeshellarg((string) $options['mu']);
        }

        if (isset($options['locale']) && $options['locale'] !== '') {
            $command .= ' --locale='.escapeshellarg((string) $options['locale']);
        }

        if (isset($options['admin_user']) && $options['admin_user'] !== '') {
            $command .= ' --admin-user='.escapeshellarg((string) $options['admin_user']);
        }

        if (isset($options['admin_pass']) && $options['admin_pass'] !== '') {
            $command .= ' --admin-pass='.escapeshellarg((string) $options['admin_pass']);
        }

        if (isset($options['admin_email']) && $options['admin_email'] !== '') {
            $command .= ' --admin-email='.escapeshellarg((string) $options['admin_email']);
        }

        if ($effectiveType === 'wp') {
            $command .= ' --yes';
        }

        if (isset($options['skip_install']) && $options['skip_install'] === true) {
            $command .= ' --skip-install';
        }

        if (isset($options['skip_content']) && $options['skip_content'] === true) {
            $command .= ' --skip-content';
        }

        if (isset($options['local_db']) && $options['local_db'] === true) {
            $command .= ' --local-db';
        }

        if (isset($options['alias_domains']) && $options['alias_domains'] !== '') {
            $command .= ' --alias-domains='.escapeshellarg((string) $options['alias_domains']);
        }

        return $this->withPTYShell($command);
    }

    /**
     * Build command to delete a site.
     *
     * --yes is mandatory: without it EasyEngine waits for interactive confirmation
     * that never arrives over SSH.
     */
    public function buildDeleteSite(string $domain): string
    {
        return $this->withPTYShell('sudo ee site delete '.escapeshellarg($domain).' --yes');
    }

    /**
     * Build command to get site info.
     */
    public function buildSiteInfo(string $domain): string
    {
        return $this->withLoginShell('sudo ee site info '.escapeshellarg($domain).' --format=json');
    }

    /**
     * Build command to clean a site cache.
     */
    public function buildCleanSite(string $domain): string
    {
        return $this->withPTYShell('sudo ee site clean '.escapeshellarg($domain));
    }

    /**
     * Build command to enable or disable a site.
     */
    public function buildToggleSite(string $domain, bool $enable): string
    {
        $action = $enable ? 'enable' : 'disable';

        return $this->withPTYShell("sudo ee site {$action} ".escapeshellarg($domain));
    }

    /**
     * Build one or more update commands for a site.
     *
     * EasyEngine does not support combining multiple configuration flags in a
     * single `ee site update` invocation — each change must be applied as a
     * separate command. This method produces one PTY-wrapped command per option
     * group, in the following execution order:
     *
     *  1. PHP version            --php=<version>
     *  2. Proxy cache            --proxy-cache=<on|off> [--proxy-cache-max-size] [--proxy-cache-max-time]
     *  3. Remove alias domains   --delete-alias-domains=<domains>
     *  4. Add alias domains      --add-alias-domains=<domains>
     *  5. SSL certificate        --ssl=<provider> [--wildcard]
     *
     * @param  array<string, mixed>  $options
     * @return list<string> One PTY-wrapped command per option group
     */
    public function buildUpdateSiteCommands(string $domain, array $options = []): array
    {
        $commands = [];

        if (isset($options['php_version']) && $options['php_version'] !== '') {
            $commands[] = $this->withPTYShell(
                'sudo ee site update '.escapeshellarg($domain)
                .' --php='.escapeshellarg((string) $options['php_version'])
            );
        }

        if (isset($options['proxy_cache']) && $options['proxy_cache'] !== '') {
            $cmd = 'sudo ee site update '.escapeshellarg($domain)
                .' --proxy-cache='.escapeshellarg((string) $options['proxy_cache']);

            if ((string) $options['proxy_cache'] === 'on') {
                if (isset($options['proxy_cache_max_size']) && $options['proxy_cache_max_size'] !== '') {
                    $cmd .= ' --proxy-cache-max-size='.escapeshellarg((string) $options['proxy_cache_max_size']);
                }
                if (isset($options['proxy_cache_max_time']) && $options['proxy_cache_max_time'] !== '') {
                    $cmd .= ' --proxy-cache-max-time='.escapeshellarg((string) $options['proxy_cache_max_time']);
                }
            }

            $commands[] = $this->withPTYShell($cmd);
        }

        if (! empty($options['delete_alias_domains'])) {
            $commands[] = $this->withPTYShell(
                'sudo ee site update '.escapeshellarg($domain)
                .' --delete-alias-domains='.escapeshellarg((string) $options['delete_alias_domains'])
            );
        }

        if (! empty($options['add_alias_domains'])) {
            $commands[] = $this->withPTYShell(
                'sudo ee site update '.escapeshellarg($domain)
                .' --add-alias-domains='.escapeshellarg((string) $options['add_alias_domains'])
            );
        }

        if (isset($options['ssl']) && $options['ssl'] !== '') {
            $cmd = 'sudo ee site update '.escapeshellarg($domain)
                .' --ssl='.escapeshellarg((string) $options['ssl']);

            if (! empty($options['wildcard'])) {
                $cmd .= ' --wildcard';
            }

            $commands[] = $this->withPTYShell($cmd);
        }

        return $commands;
    }

    /**
     * Wrap a command in a login shell.
     *
     * Ensures /etc/profile and ~/.bash_profile are sourced so PATH and Docker
     * environment variables are available. Used for read-only commands that return
     * structured (JSON) output and must not have TTY noise.
     */
    private function withLoginShell(string $command): string
    {
        return 'bash -l -c '.escapeshellarg($command);
    }

    /**
     * Wrap a command in a login shell that allocates a pseudo-TTY via `script`.
     *
     * EasyEngine state-changing commands (clean, delete, create, enable, disable,
     * update) call `docker exec` internally. Without a PTY those exec calls complete
     * without propagating the operation inside the container. `script -q -c '...'
     * /dev/null` allocates a pseudo-TTY in the remote shell, making Docker believe
     * it is attached to a real terminal — identical to running `ssh -t host cmd`.
     *
     * phpseclib's enablePTY() is NOT used here because it is incompatible with
     * exec() callbacks, which would break real-time output streaming. Allocating
     * the PTY at the shell layer (via `script`) avoids that limitation entirely.
     *
     * Structure: bash -l -c 'script -q -c "<command>" /dev/null'
     *   • bash -l   loads the login environment (PATH, etc.)
     *   • script -q suppresses "Script started/done" messages
     *   • /dev/null  discards the typescript recording file
     *   • exit code of `script` equals the exit code of the inner command
     */
    private function withPTYShell(string $command): string
    {
        return 'bash -l -c '.escapeshellarg('script -q -c '.escapeshellarg($command).' /dev/null');
    }
}

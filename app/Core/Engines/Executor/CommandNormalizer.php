<?php

declare(strict_types=1);

namespace App\Core\Engines\Executor;

use App\Core\Engines\Exceptions\CommandExecutionException;

/**
 * Validates and sanitizes high-level inputs for command execution.
 *
 * Ensures that all parameters passed to engines are valid,
 * safe, and properly formatted before execution.
 */
final class CommandNormalizer
{
    /**
     * Validate and normalize domain name.
     *
     * @param  string  $domain  Domain name to validate
     * @return string Normalized domain name
     *
     * @throws CommandExecutionException
     */
    public function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));

        // Remove protocol if present
        $domain = preg_replace('#^https?://#i', '', $domain);

        // Remove trailing slash
        $domain = rtrim($domain, '/');

        // Remove www prefix if present
        $domain = preg_replace('#^www\.#i', '', $domain);

        // Validate domain format
        if (! $this->isValidDomain($domain)) {
            throw new CommandExecutionException("Invalid domain format: {$domain}");
        }

        return $domain;
    }

    /**
     * Validate domain format.
     *
     * @param  string  $domain  Domain to validate
     * @return bool True if valid
     */
    private function isValidDomain(string $domain): bool
    {
        // Basic domain validation
        if (empty($domain) || strlen($domain) > 253) {
            return false;
        }

        // Check for valid characters and structure
        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i';

        return preg_match($pattern, $domain) === 1;
    }

    /**
     * Validate and normalize action name.
     *
     * @param  string  $action  Action name to validate
     * @return string Normalized action name
     *
     * @throws CommandExecutionException
     */
    public function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));

        // Only allow alphanumeric and underscores
        if (! preg_match('/^[a-z0-9_]+$/', $action)) {
            throw new CommandExecutionException("Invalid action name format: {$action}");
        }

        return $action;
    }

    /**
     * Validate and normalize PHP version.
     *
     * @param  string  $version  PHP version string
     * @return string Normalized version (e.g., '8.3', '8.2')
     *
     * @throws CommandExecutionException
     */
    public function normalizePhpVersion(string $version): string
    {
        $version = trim($version);

        // Valid PHP versions for engines
        $validVersions = ['8.5', '8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];

        if (! in_array($version, $validVersions, true)) {
            throw new CommandExecutionException(
                "Invalid PHP version: {$version}. Supported versions: ".implode(', ', $validVersions)
            );
        }

        return $version;
    }

    /**
     * Validate and normalize email address.
     *
     * @param  string  $email  Email address to validate
     * @return string Normalized email address
     *
     * @throws CommandExecutionException
     */
    public function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new CommandExecutionException("Invalid email address format: {$email}");
        }

        return $email;
    }

    /**
     * Validate and normalize site type.
     *
     * @param  string  $type  Site type (wp, html, php)
     * @return string Normalized site type
     *
     * @throws CommandExecutionException
     */
    public function normalizeSiteType(string $type): string
    {
        $type = strtolower(trim($type));

        $validTypes = ['wordpress', 'html', 'php'];

        if (! in_array($type, $validTypes, true)) {
            throw new CommandExecutionException(
                "Invalid site type: {$type}. Supported types: ".implode(', ', $validTypes)
            );
        }

        return $type;
    }

    /**
     * Validate and normalize action parameters.
     *
     * @param  string  $action  Action name
     * @param  array<string, mixed>  $parameters  Raw parameters
     * @return array<string, mixed> Normalized parameters
     *
     * @throws CommandExecutionException
     */
    public function normalizeParameters(string $action, array $parameters): array
    {
        $normalized = [];

        // Normalize domain if present
        if (isset($parameters['domain'])) {
            $normalized['domain'] = $this->normalizeDomain((string) $parameters['domain']);
        }

        // Normalize options if present
        if (isset($parameters['options']) && is_array($parameters['options'])) {
            $normalized['options'] = $this->normalizeOptions($parameters['options']);
        }

        // Copy other safe parameters
        foreach ($parameters as $key => $value) {
            if (! isset($normalized[$key]) && $key !== 'options') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Normalize site creation/update options.
     *
     * @param  array<string, mixed>  $options  Raw options
     * @return array<string, mixed> Normalized options
     *
     * @throws CommandExecutionException
     */
    private function normalizeOptions(array $options): array
    {
        $normalized = [];

        if (isset($options['php_version'])) {
            $normalized['php_version'] = $this->normalizePhpVersion((string) $options['php_version']);
        }

        if (isset($options['type'])) {
            $normalized['type'] = $this->normalizeSiteType((string) $options['type']);
        }

        if (isset($options['admin_email'])) {
            $normalized['admin_email'] = $this->normalizeEmail((string) $options['admin_email']);
        }

        // Boolean options
        foreach (['ssl', 'cache'] as $boolOption) {
            if (isset($options[$boolOption])) {
                $normalized[$boolOption] = (bool) $options[$boolOption];
            }
        }

        return $normalized;
    }
}

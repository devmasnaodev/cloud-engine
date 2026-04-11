<?php

declare(strict_types=1);

namespace App\Core\Servers;

final class ServerInfo
{
    /**
     * Detect OS information by reading /etc/os-release where available.
     * Returns keys: id, pretty_name, version_id
     *
     * @return array<string,string|null>
     */
    public static function detect(): array
    {
        $result = [
            'id' => null,
            'pretty_name' => null,
            'version_id' => null,
        ];

        $path = '/etc/os-release';

        if (is_readable($path)) {
            $contents = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($contents as $line) {
                if (strpos($line, '=') === false) {
                    continue;
                }

                [$k, $v] = explode('=', $line, 2);
                $v = trim($v, " \t\n\r\"'");

                if (in_array($k, ['ID', 'PRETTY_NAME', 'VERSION_ID'], true)) {
                    $key = strtolower($k === 'PRETTY_NAME' ? 'pretty_name' : $k);
                    $result[$key] = $v;
                }
            }
        }

        return $result;
    }

    public static function isSupportedUbuntuVersion(array $info): bool
    {
        if (empty($info['id']) || strtolower((string) $info['id']) !== 'ubuntu') {
            return false;
        }

        if (empty($info['version_id'])) {
            return false;
        }

        $version = (string) $info['version_id'];

        // Support Ubuntu 22.* and 24.* as requested
        return str_starts_with($version, '22') || str_starts_with($version, '24');
    }

    /**
     * Parse the contents of an /etc/os-release formatted string and return
     * the same shape as detect(). Useful when reading the file remotely.
     *
     * @return array<string,string|null>
     */
    public static function parseFromString(string $contents): array
    {
        $result = [
            'id' => null,
            'pretty_name' => null,
            'version_id' => null,
        ];

        $lines = preg_split('/\r?\n/', $contents, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($lines as $line) {
            if (strpos($line, '=') === false) {
                continue;
            }

            [$k, $v] = explode('=', $line, 2);
            $v = trim($v, " \t\n\r\"'");

            if (in_array($k, ['ID', 'PRETTY_NAME', 'VERSION_ID'], true)) {
                $key = strtolower($k === 'PRETTY_NAME' ? 'pretty_name' : $k);
                $result[$key] = $v;
            }
        }

        return $result;
    }
}

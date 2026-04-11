<?php

declare(strict_types=1);

namespace App\Core\Servers\Services;

use App\Core\Servers\Contracts\RemoteCommandExecutorInterface;
use App\Core\Servers\Models\Server;
use App\Core\Servers\ServerInfo;

/**
 * Detects OS information on a remote server by reading /etc/os-release via SSH.
 * Also validates whether the detected OS is supported for provisioning.
 */
final class ServerInfoDetector
{
    public function __construct(
        private readonly RemoteCommandExecutorInterface $executor,
    ) {}

    /**
     * Detect remote OS info for the given server.
     *
     * @return array{id: string|null, pretty_name: string|null, version_id: string|null}
     */
    public function detect(Server $server): array
    {
        try {
            $result = $this->executor->execute($server, 'cat /etc/os-release');
        } catch (\Throwable) {
            return ['id' => null, 'pretty_name' => null, 'version_id' => null];
        }

        $output = trim($result->stdout);

        if ($output === '') {
            return ['id' => null, 'pretty_name' => null, 'version_id' => null];
        }

        return ServerInfo::parseFromString($output);
    }

    /**
     * Check whether the provided server info is supported by this provisioner.
     *
     * @param  array<string, string|null>  $info
     */
    public function isSupported(array $info): bool
    {
        return ServerInfo::isSupportedUbuntuVersion($info);
    }
}

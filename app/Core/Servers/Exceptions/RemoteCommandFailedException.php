<?php

declare(strict_types=1);

namespace App\Core\Servers\Exceptions;

use App\Core\Drivers\SSH\SSHCommandResult;
use App\Core\Servers\Models\Server;

final class RemoteCommandFailedException extends \RuntimeException
{
    public function __construct(
        public readonly Server $server,
        public readonly SSHCommandResult $result
    ) {
        parent::__construct(sprintf(
            'Remote command failed on server %s (#%d) with exit code %d: %s',
            $server->name,
            $server->id,
            $result->exitStatus,
            trim($result->stderr) !== '' ? trim($result->stderr) : '(no stderr)'
        ));
    }
}

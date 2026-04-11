<?php

declare(strict_types=1);

namespace App\Core\Servers\Contracts;

use App\Core\Drivers\SSH\SSHDriver;
use App\Core\Servers\Models\Server;

interface ServerConnectionServiceInterface
{
    public function connect(Server $server): SSHDriver;

    public function testConnection(Server $server): bool;
}

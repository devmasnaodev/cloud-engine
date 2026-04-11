<?php

declare(strict_types=1);

namespace App\Core\Engines\Validators;

use App\Core\Drivers\SSH\SSHDriver;

interface EngineValidatorInterface
{
    /**
     * Validate engine presence on the remote server using an existing SSH connection.
     */
    public function validate(SSHDriver $driver): bool;
}

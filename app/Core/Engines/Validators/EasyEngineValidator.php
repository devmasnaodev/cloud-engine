<?php

declare(strict_types=1);

namespace App\Core\Engines\Validators;

use App\Core\Drivers\SSH\SSHDriver;

final class EasyEngineValidator implements EngineValidatorInterface
{
    public function validate(SSHDriver $driver): bool
    {
        $result = $driver->execute('sudo ee cli version');

        return $result->exitStatus === 0;
    }
}

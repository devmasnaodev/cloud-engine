<?php

declare(strict_types=1);

namespace App\Core\Engines\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a command execution fails.
 *
 * This exception is thrown when:
 * - A command returns a non-zero exit status
 * - The command cannot be built or validated
 * - The SSH connection fails during execution
 * - Invalid parameters are provided to an action
 */
final class CommandExecutionException extends RuntimeException
{
    /**
     * Create exception for invalid action parameters.
     *
     * @param  string  $action  Action name
     * @param  string  $parameter  Missing parameter name
     */
    public static function missingParameter(string $action, string $parameter): self
    {
        return new self("Required parameter '{$parameter}' missing for action '{$action}'");
    }

    /**
     * Create exception for unsupported action.
     *
     * @param  string  $action  Action name
     * @param  string  $engine  Engine name
     */
    public static function unsupportedAction(string $action, string $engine): self
    {
        return new self("Action '{$action}' is not supported by engine '{$engine}'");
    }

    /**
     * Create exception for command failure.
     *
     * @param  string  $command  The command that failed
     * @param  int  $exitStatus  Exit status code
     * @param  string  $stderr  Error output
     */
    public static function commandFailed(string $command, int $exitStatus, string $stderr): self
    {
        return new self(
            "Command failed with exit code {$exitStatus}:\nCommand: {$command}\nError: {$stderr}"
        );
    }
}

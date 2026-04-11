<?php

declare(strict_types=1);

namespace App\Core\Commands;

interface CommandInterface
{
    public function id(): string;

    public function name(): string;

    public function description(): string;

    public function command(): string;

    /**
     * Optional environment variables for the command
     *
     * @return array<string,string>
     */
    public function env(): array;

    public function interactive(): bool;

    /**
     * Validate a command execution result. Return an error message when validation
     * fails, or null when OK. Implementations may return special-prefixed
     * messages to alter execution behavior:
     *  - return `STOP: reason` to halt the recipe and exit successfully
     *  - return `SKIP: reason` to skip this step without failing the recipe
     *  - return `WARN: reason` to emit a warning but continue
     * Otherwise return a plain error message to abort execution.
     *
     * @param  object  $result  // expected to have stdout and stderr properties
     */
    public function validateResult(object $result): ?string;

    /**
     * Convert recipe to the associative array format previously used
     * by InitialServerSetup::steps(). Keeps backward compatibility.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array;
}

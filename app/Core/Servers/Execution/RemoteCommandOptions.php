<?php

declare(strict_types=1);

namespace App\Core\Servers\Execution;

final class RemoteCommandOptions
{
    /**
     * @param  array<string, string>  $env
     */
    public function __construct(
        public readonly ?string $cwd = null,
        public readonly array $env = [],
        public readonly ?string $secret = null,
        public readonly bool $nothrow = true,
        public readonly ?int $timeout = null,
        public readonly ?int $idleTimeout = null,
        public readonly bool $forceOutput = false,
    ) {}

    public static function raw(): self
    {
        return new self;
    }

    /**
     * @param  array<string, string>  $env
     */
    public static function fromArray(array $env = [], ?string $cwd = null, ?int $timeout = null): self
    {
        return new self(cwd: $cwd, env: $env, timeout: $timeout);
    }
}

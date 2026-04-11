<?php

declare(strict_types=1);

namespace App\Core\Engines\Validators;

final class EngineValidatorResolver
{
    /**
     * @param  array<string, EngineValidatorInterface>  $map
     */
    public function __construct(private readonly array $map = []) {}

    public function getValidator(string $engine): ?EngineValidatorInterface
    {
        return $this->map[$engine] ?? null;
    }
}

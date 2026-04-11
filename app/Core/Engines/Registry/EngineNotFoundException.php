<?php

declare(strict_types=1);

namespace App\Core\Engines\Registry;

use RuntimeException;

/**
 * Thrown when an engine is requested from the registry but is not registered.
 */
final class EngineNotFoundException extends RuntimeException
{
    public static function forName(string $name): self
    {
        return new self("No engine registered under the name '{$name}'.");
    }
}

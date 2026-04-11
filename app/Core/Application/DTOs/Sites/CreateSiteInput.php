<?php

declare(strict_types=1);

namespace App\Core\Application\DTOs\Sites;

/**
 * Input data for the CreateSiteUseCase.
 *
 * @property-read array<string, mixed> $options  Engine-specific site options (type, ssl, php, etc.)
 */
final readonly class CreateSiteInput
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $serverId,
        public string $domain,
        public array $options = [],
    ) {}
}

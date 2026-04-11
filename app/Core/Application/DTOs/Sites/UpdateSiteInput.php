<?php

declare(strict_types=1);

namespace App\Core\Application\DTOs\Sites;

/**
 * Input data for the UpdateSiteUseCase.
 *
 * @property-read array<string, mixed> $options  Engine-specific update options (ssl, php, alias-domains, etc.)
 */
final readonly class UpdateSiteInput
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public int $serverId,
        public int $siteId,
        public string $domain,
        public array $options,
    ) {}
}

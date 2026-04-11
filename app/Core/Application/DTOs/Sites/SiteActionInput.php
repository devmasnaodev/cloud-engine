<?php

declare(strict_types=1);

namespace App\Core\Application\DTOs\Sites;

/**
 * Input data for simple site actions that only require a domain.
 *
 * Used for: enable_site, disable_site, clean_site, delete_site.
 */
final readonly class SiteActionInput
{
    public function __construct(
        public int $serverId,
        public int $siteId,
        public string $domain,
        public string $action,
    ) {}
}

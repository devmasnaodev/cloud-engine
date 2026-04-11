<?php

declare(strict_types=1);

namespace App\Core\Shell\Bash\Commands\Config;

use App\Core\Commands\AbstractCommand;

final class Ufw extends AbstractCommand
{
    /** @var array<int,string> */
    private array $rules;

    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? $this->defaultRules();
    }

    private function defaultRules(): array
    {
        return ['OpenSSH', 'http', 'https'];
    }

    public function id(): string
    {
        return 'ufw';
    }

    public function name(): string
    {
        return 'Configure UFW';
    }

    public function description(): string
    {
        return 'Allow OpenSSH, HTTP and HTTPS and enable firewall';
    }

    public function command(): string
    {
        $commands = array_map(fn ($rule) => "ufw allow $rule", $this->rules);
        $commands[] = 'ufw --force enable';

        return implode(' && ', $commands);
    }
}

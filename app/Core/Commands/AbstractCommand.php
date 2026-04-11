<?php

declare(strict_types=1);

namespace App\Core\Commands;

abstract class AbstractCommand implements CommandInterface
{
    public function env(): array
    {
        return [];
    }

    public function interactive(): bool
    {
        return false;
    }

    public function toArray(): array
    {
        $arr = [
            'id' => $this->id(),
            'name' => $this->name(),
            'description' => $this->description(),
            'command' => $this->command(),
            'interactive' => $this->interactive(),
        ];

        $env = $this->env();

        if (! empty($env)) {
            $arr['env'] = $env;
        }

        return $arr;
    }

    public function validateResult(object $result): ?string
    {
        return null;
    }
}

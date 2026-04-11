<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

final class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        return [
            'server_id' => \App\Models\Server::factory(),
            'domain' => $this->faker->domainName(),
            'info' => [
                'site' => $this->faker->domainName(),
                'status' => 'active',
            ],
        ];
    }
}

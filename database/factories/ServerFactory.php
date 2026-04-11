<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Server>
 */
class ServerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = \App\Models\Server::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a test SSH key pair
        $privateKey = <<<'KEY'
-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAMwAAAAtzc2gtZW
QyNTUxOQAAACDlFFh0jOwlvHk53jsBByg73uUEDZmWYkVZ0WNMMEo4QgAAAJjyFdeQ8hXX
kAAAAAtzc2gtZWQyNTUxOQAAACDlFFh0jOwlvHk53jsBByg73uUEDZmWYkVZ0WNMMEo4Qg
AAAECsfO7j0KWpcYy1vbQ3BNJQaoqD/n4vP1BVgUfcKF3rY+UUWHSM7CW8eTneOwEHKDve
5QQNmZZiRVnRY0wwSjhCAAAADmdpdGh1Yi1hY3Rpb25zAQIDBAUGBw==
-----END OPENSSH PRIVATE KEY-----
KEY;

        return [
            'name' => fake()->words(2, true).' Server',
            'ip_address' => fake()->ipv4(),
            'ssh_port' => 22,
            'ssh_users' => [[
                'username' => 'infoadm',
                'encrypted_private_key' => encrypt($privateKey),
            ]],
            'ssh_execution_username' => 'infoadm',
            'provisioning_engine' => 'easyengine',
            'is_active' => true,
            'last_connected_at' => null,
        ];
    }

    /**
     * Indicate that the server is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific IP address.
     */
    public function withIp(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ip,
        ]);
    }

    /**
     * Set a specific private key.
     */
    public function withPrivateKey(string $privateKey): static
    {
        $encryptedPrivateKey = encrypt($privateKey);

        return $this->state(fn (array $attributes) => [
            'ssh_users' => [[
                'username' => $attributes['ssh_execution_username'] ?? 'infoadm',
                'encrypted_private_key' => $encryptedPrivateKey,
            ]],
        ]);
    }
}

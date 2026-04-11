<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Server Eloquent model for persistence.
 *
 * This is the ORM representation of the Server entity.
 * The domain model is at App\Core\Servers\Models\Server.
 *
 * @property int $id
 * @property string $name
 * @property string $ip_address
 * @property int $ssh_port
 * @property array<int, array{username: string, encrypted_private_key: string}>|null $ssh_users
 * @property string|null $ssh_execution_username
 * @property string|null $provisioning_engine
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_connected_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class Server extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'ip_address',
        'ssh_port',
        'ssh_users',
        'ssh_execution_username',
        'provisioning_engine',
        'is_active',
        'last_connected_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ssh_port' => 'integer',
            'ssh_users' => 'array',
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }

    /**
     * Get sites belonging to this server.
     */
    public function sites(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function provisioningRuns(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProvisioningRun::class);
    }

    public function toDomainModel(): \App\Core\Servers\Models\Server
    {
        $sshUsers = $this->resolveSshUsers();

        return new \App\Core\Servers\Models\Server(
            id: $this->id,
            name: $this->name,
            ipAddress: $this->ip_address,
            sshPort: $this->ssh_port,
            sshUsers: $sshUsers,
            sshExecutionUsername: $this->resolveExecutionUsername($sshUsers),
            provisioningEngine: $this->provisioning_engine,
            isActive: $this->is_active,
            createdAt: new \DateTimeImmutable($this->created_at->toDateTimeString()),
            lastConnectedAt: $this->last_connected_at
                ? new \DateTimeImmutable($this->last_connected_at->toDateTimeString())
                : null
        );
    }

    /**
     * @return array<int, array{username: string, encrypted_private_key: string}>
     */
    private function resolveSshUsers(): array
    {
        $sshUsers = is_array($this->ssh_users) ? $this->ssh_users : [];

        $normalized = array_values(array_filter(array_map(
            static function (mixed $sshUser): ?array {
                if (! is_array($sshUser)) {
                    return null;
                }

                $username = $sshUser['username'] ?? null;
                $encryptedPrivateKey = $sshUser['encrypted_private_key'] ?? null;

                if (! is_string($username) || $username === '') {
                    return null;
                }

                if (! is_string($encryptedPrivateKey) || $encryptedPrivateKey === '') {
                    return null;
                }

                return [
                    'username' => $username,
                    'encrypted_private_key' => $encryptedPrivateKey,
                ];
            },
            $sshUsers
        )));

        if ($normalized !== []) {
            return $normalized;
        }

        if (is_string($this->ssh_username) && $this->ssh_username !== '' && is_string($this->encrypted_private_key) && $this->encrypted_private_key !== '') {
            return [[
                'username' => $this->ssh_username,
                'encrypted_private_key' => $this->encrypted_private_key,
            ]];
        }

        return [];
    }

    /**
     * @param  array<int, array{username: string, encrypted_private_key: string}>  $sshUsers
     */
    private function resolveExecutionUsername(array $sshUsers): string
    {
        if (is_string($this->ssh_execution_username) && $this->ssh_execution_username !== '') {
            return $this->ssh_execution_username;
        }

        return $sshUsers[0]['username'] ?? 'root';
    }
}

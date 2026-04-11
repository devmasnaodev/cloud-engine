<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $server_id
 * @property string $recipe_id
 * @property string $recipe_name
 * @property string|null $execution_username
 * @property string $status pending|running|completed|failed
 * @property array<int, array<string, mixed>>|null $steps
 * @property string|null $failure_reason
 * @property float|null $total_duration
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class ProvisioningRun extends Model
{
    protected $fillable = [
        'server_id',
        'recipe_id',
        'recipe_name',
        'execution_username',
        'status',
        'steps',
        'current_step',
        'failure_reason',
        'total_duration',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'current_step' => 'array',
            'total_duration' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }
}

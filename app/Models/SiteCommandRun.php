<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SiteCommandRun Eloquent model.
 *
 * Records asynchronous site management commands (create, enable, disable, delete)
 * dispatched to remote servers via the EasyEngine engine.
 *
 * @property int $id
 * @property int $server_id
 * @property int|null $site_id
 * @property string $action
 * @property string $domain
 * @property string $command
 * @property array|null $parameters
 * @property string $status
 * @property string|null $stdout
 * @property string|null $stderr
 * @property int|null $exit_status
 * @property string|null $partial_stdout
 * @property float|null $duration
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
final class SiteCommandRun extends Model
{
    protected $fillable = [
        'server_id',
        'site_id',
        'action',
        'domain',
        'command',
        'parameters',
        'status',
        'stdout',
        'stderr',
        'exit_status',
        'partial_stdout',
        'duration',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}

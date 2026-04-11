<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site Eloquent model.
 *
 * @property int $id
 * @property int $server_id
 * @property string $domain
 * @property array|null $info
 */
final class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'domain',
        'info',
    ];

    protected function casts(): array
    {
        return [
            'info' => 'array',
        ];
    }

    public function server(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RedExplosion\Sqids\Concerns\HasSqids;

class WebhookLog extends Model
{
    /** @use HasFactory<\Database\Factories\WebhookLogFactory> */
    use HasFactory, HasSqids;

    protected $fillable = [
        'webhook_id',
        'method',
        'ip_address',
        'user_agent',
        'headers',
        'query_params',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'query_params' => 'array',
        ];
    }

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}

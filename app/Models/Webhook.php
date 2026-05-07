<?php

namespace App\Models;

use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RedExplosion\Sqids\Concerns\HasSqids;

class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory, HasSqids;

    protected $fillable = ['user_id', 'name', 'slug', 'token'];

    protected $appends = ['sqid'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Session extends Model
{
    public const ONLINE_THRESHOLD_SECONDS = 300;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $table = 'sessions';

    protected $guarded = [];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Session>  $query
     * @return Builder<Session>
     */
    public function scopeActive(Builder $query): Builder
    {
        $lifetimeSeconds = (int) config('session.lifetime') * 60;

        return $query
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->timestamp - $lifetimeSeconds);
    }

    public function isOnline(): bool
    {
        return $this->last_activity >= now()->timestamp - self::ONLINE_THRESHOLD_SECONDS;
    }

    public function lastActivityAt(): Carbon
    {
        return Carbon::createFromTimestamp($this->last_activity);
    }

    public function deviceLabel(): string
    {
        return User::deviceLabelFromUserAgent($this->user_agent);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenMessageReply extends Model
{
    /** @use HasFactory<\Database\Factories\ScreenMessageReplyFactory> */
    use HasFactory;

    protected $fillable = [
        'screen_message_id',
        'user_id',
        'body',
    ];

    /**
     * @return BelongsTo<ScreenMessage, $this>
     */
    public function screenMessage(): BelongsTo
    {
        return $this->belongsTo(ScreenMessage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

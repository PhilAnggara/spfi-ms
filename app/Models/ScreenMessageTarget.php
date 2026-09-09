<?php

namespace App\Models;

use App\Enums\ScreenMessageTargetType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreenMessageTarget extends Model
{
    protected $fillable = [
        'screen_message_id',
        'target_type',
        'target_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => ScreenMessageTargetType::class,
            'target_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ScreenMessage, $this>
     */
    public function screenMessage(): BelongsTo
    {
        return $this->belongsTo(ScreenMessage::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prs extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'prs';
    protected $guarded = [
        'id'
    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'department_id' => 'integer',
    ];
    protected $hidden = [

    ];

    public function items()
    {
        return $this->hasMany(PrsItem::class, 'prs_id', 'id');
    }
    public function logs()
    {
        return $this->hasMany(PrsLog::class, 'prs_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'id');
    }

    /**
     * Get overall delivery status for PRS
     * Possible values: PENDING, PARTIAL, RECEIVED
     */
    public function getOverallDeliveryStatusAttribute()
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 'PENDING';
        }

        $statuses = $items->pluck('delivery_status')->unique();

        if ($statuses->count() === 1 && $statuses->first() === 'RECEIVED') {
            return 'RECEIVED';
        } elseif ($statuses->contains('RECEIVED') || $statuses->contains('PARTIAL')) {
            return 'PARTIAL';
        } else {
            return 'PENDING';
        }
    }

    /**
     * Get overall delivery progress percentage
     */
    public function getOverallDeliveryProgressAttribute()
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0;
        }

        $totalProgress = $items->sum('delivery_progress');
        return (int)($totalProgress / $items->count());
    }

    /**
     * Check if all items are fully received
     */
    public function isDeliveryComplete()
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(function ($item) {
            return $item->delivery_status === 'RECEIVED';
        });
    }

    /**
     * Auto-update PRS status to DELIVERY_COMPLETE if all items received
     */
    public function checkAndUpdateDeliveryStatus()
    {
        if ($this->isDeliveryComplete() && $this->status !== 'DELIVERY_COMPLETE') {
            $this->update(['status' => 'DELIVERY_COMPLETE']);
            return true;
        }
        return false;
    }
}

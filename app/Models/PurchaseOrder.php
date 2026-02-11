<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'supplier_id',
        'created_by',
        'status',
        'po_number',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'fees',
        'total',
        'certified_by_user_id',
        'approved_by_user_id',
        'submitted_at',
        'approved_at',
        'approval_notes',
        'signature_meta',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        // Snapshot of signature names/titles for print.
        'signature_meta' => 'array',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certifiedBy()
    {
        return $this->belongsTo(User::class, 'certified_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}

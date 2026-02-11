<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Currency;

class PurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'supplier_id',
        'currency_id',
        'created_by',
        'status',
        'po_number',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_rate',
        'discount_amount',
        'ppn_rate',
        'ppn_amount',
        'pph_rate',
        'pph_amount',
        'fees',
        'total',
        'remark_type',
        'remark_text',
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

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
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

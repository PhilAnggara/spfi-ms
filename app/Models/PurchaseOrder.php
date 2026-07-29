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
        'fees_breakdown',
        'total',
        'remark_type',
        'remark_text',
        'term_of_payment_type',
        'term_of_payment',
        'term_of_delivery',
        'certified_by_user_id',
        'approved_by_user_id',
        'submitted_at',
        'approved_at',
        'approval_notes',
        'signature_meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'supplier_id' => 'integer',
        'currency_id' => 'integer',
        'created_by' => 'integer',
        'certified_by_user_id' => 'integer',
        'approved_by_user_id' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'fees_breakdown' => 'array',
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

    public function receivingReports()
    {
        return $this->hasMany(ReceivingReport::class, 'purchase_order_id');
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

    /**
     * High-level approval is required for non-threshold currencies, or when the
     * IDR total meets/exceeds the configured threshold.
     */
    public function requiresHighLevelApproval(): bool
    {
        $thresholdCurrency = strtoupper((string) config('purchase-order.signature.threshold_currency', 'IDR'));
        $currencyCode = strtoupper((string) ($this->currency?->code ?? $thresholdCurrency));

        if ($currencyCode !== $thresholdCurrency) {
            return true;
        }

        $approvalThreshold = (float) config('purchase-order.signature.approval_threshold', 4000000);

        return (float) $this->total >= $approvalThreshold;
    }

    public function printCertifiedByName(): string
    {
        return (string) config('purchase-order.signature.certified_by_name', 'Denny Tuhatelu');
    }

    public function printApprovedByName(): string
    {
        if ($this->requiresHighLevelApproval()) {
            return (string) config(
                'purchase-order.signature.approved_by_at_or_above_threshold_name',
                'S.C Calamba, Jr'
            );
        }

        return (string) config(
            'purchase-order.signature.approved_by_below_threshold_name',
            'Denny Tuhatelu'
        );
    }
}

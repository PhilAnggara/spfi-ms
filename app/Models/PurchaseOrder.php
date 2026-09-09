<?php

namespace App\Models;

use App\Enums\PoReceiptStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

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
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id')
            ->orderBy('id');
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

    /**
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeWithReceiptQuantitySelects(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        if ($query->getQuery()->columns === null) {
            $query->select("{$table}.*");
        }

        return $query
            ->selectRaw(PoReceiptStatus::qtyOrderedSubquerySql($table).' as qty_ordered')
            ->selectRaw(PoReceiptStatus::qtyReceivedSubquerySql($table).' as qty_received');
    }

    public function ensureReceiptQuantities(): static
    {
        if (! array_key_exists('qty_ordered', $this->attributes)) {
            $this->attributes['qty_ordered'] = (float) $this->items()->sum('quantity');
        }

        if (! array_key_exists('qty_received', $this->attributes)) {
            $this->attributes['qty_received'] = (float) DB::table('receiving_report_items as rri')
                ->join('receiving_reports as rr', 'rr.id', '=', 'rri.receiving_report_id')
                ->where('rr.purchase_order_id', $this->getKey())
                ->whereNull('rr.deleted_at')
                ->whereNull('rri.deleted_at')
                ->sum(DB::raw('rri.qty_good + rri.qty_bad'));
        }

        return $this;
    }

    public function receiptStatus(): PoReceiptStatus
    {
        $this->ensureReceiptQuantities();

        return PoReceiptStatus::fromQuantities(
            (float) ($this->attributes['qty_ordered'] ?? 0),
            (float) ($this->attributes['qty_received'] ?? 0),
        );
    }

    public function receiptQuantitySummary(): string
    {
        $this->ensureReceiptQuantities();

        $ordered = (float) ($this->attributes['qty_ordered'] ?? 0);
        $received = (float) ($this->attributes['qty_received'] ?? 0);

        return format_po_decimal($received).' / '.format_po_decimal($ordered);
    }
}

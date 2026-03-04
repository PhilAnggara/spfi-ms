<?php

namespace App\Models;

use App\Models\CustomsDocumentType;
use App\Models\ReceivingReportItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReceivingReport extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'receiving_reports';

    protected $fillable = [
        'rr_number',
        'purchase_order_id',
        'received_date',
        'requires_customs_document',
        'customs_document_number',
        'customs_document_type_id',
        'customs_document_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'id' => 'integer',
        'purchase_order_id' => 'integer',
        'requires_customs_document' => 'boolean',
        'customs_document_type_id' => 'integer',
        'created_by' => 'integer',
        'received_date' => 'date',
        'customs_document_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function items()
    {
        return $this->hasMany(ReceivingReportItem::class, 'receiving_report_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customsDocumentType()
    {
        return $this->belongsTo(CustomsDocumentType::class, 'customs_document_type_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * In-memory encode document for Accounting Inventory UI (not a DB table).
 */
class AccountingInventoryTransaction
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ENCODED = 'encoded';

    public const STATUS_VOIDED = 'voided';

    /**
     * @var list<string>
     */
    public const DOC_TYPES = ['RR', 'TS', 'DR', 'CV', 'JV'];

    /**
     * @var list<string>
     */
    public const MANUAL_DOC_TYPES = ['CV', 'JV'];

    public ?int $id = null;

    public int $category_id = 0;

    public string $doc_type = '';

    public string $doc_number = '';

    public ?Carbon $doc_date = null;

    public ?string $po_number = null;

    public ?string $party_code = null;

    public ?string $party_name = null;

    public ?string $remarks = null;

    public string $status = self::STATUS_DRAFT;

    public bool $is_corrected = false;

    public float $total_amount = 0.0;

    public ?string $source_type = null;

    public ?int $source_id = null;

    public ?int $supplier_id = null;

    public ?int $purchase_order_id = null;

    public ?int $encoded_by = null;

    public ?Carbon $encoded_at = null;

    public ?ItemCategory $category = null;

    public ?User $encodedBy = null;

    public ?User $voidedBy = null;

    /** @var Collection<int, AccountingInventoryTransactionLine> */
    public Collection $lines;

    public function __construct()
    {
        $this->lines = collect();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes = []): self
    {
        $document = new self;

        foreach ($attributes as $key => $value) {
            if ($key === 'lines' || $key === 'category' || $key === 'encodedBy' || $key === 'voidedBy') {
                continue;
            }

            if ($key === 'doc_date' && $value !== null && ! $value instanceof Carbon) {
                $document->doc_date = Carbon::parse((string) $value);

                continue;
            }

            if ($key === 'encoded_at' && $value !== null && ! $value instanceof Carbon) {
                $document->encoded_at = Carbon::parse((string) $value);

                continue;
            }

            if (property_exists($document, $key)) {
                $document->{$key} = $value;
            }
        }

        if (isset($attributes['category']) && $attributes['category'] instanceof ItemCategory) {
            $document->category = $attributes['category'];
        }

        if (isset($attributes['encodedBy']) && $attributes['encodedBy'] instanceof User) {
            $document->encodedBy = $attributes['encodedBy'];
        }

        if (isset($attributes['lines'])) {
            $document->lines = collect($attributes['lines']);
        }

        return $document;
    }

    public function isEncoded(): bool
    {
        return $this->status === self::STATUS_ENCODED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function isManual(): bool
    {
        return in_array(strtoupper($this->doc_type), self::MANUAL_DOC_TYPES, true);
    }

    public function displayDocNumber(): string
    {
        $parts = explode('|', $this->doc_number, 3);
        if (count($parts) === 3 && in_array($parts[0], ['RR', 'TS', 'DR'], true)) {
            return $parts[1];
        }

        return $this->doc_number;
    }

    /**
     * Compatibility no-op for callers that previously eager-loaded relations.
     *
     * @param  list<string>|string  $relations
     */
    public function load(array|string $relations): self
    {
        return $this;
    }

    /**
     * @param  list<string>|string  $relations
     */
    public function loadMissing(array|string $relations): self
    {
        return $this;
    }

    public function fresh(array|string $relations = []): self
    {
        return $this;
    }
}

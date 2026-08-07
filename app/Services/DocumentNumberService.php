<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DocumentNumberService
{
    private const DEFAULT_PADDING = 6;

    private const DOCUMENTS = [
        'PO' => ['table' => 'purchase_orders', 'column' => 'po_number', 'field' => 'po_number'],
        'RR' => ['table' => 'receiving_reports', 'column' => 'rr_number', 'field' => 'rr_number'],
        'TS' => ['table' => 'transfer_slips', 'column' => 'ts_number', 'field' => 'ts_number'],
        'DR' => ['table' => 'deliveries', 'column' => 'dr_number', 'field' => 'dr_number'],
    ];

    public function previewNext(string $type): string
    {
        $last = $this->lastNumberParts($type);
        $nextRunningNumber = $last['running_number'] + 1;
        $padding = $last['running_number'] > 0 ? $last['padding'] : self::DEFAULT_PADDING;
        $attempts = 0;

        do {
            $number = $last['prefix'].str_pad((string) $nextRunningNumber, $padding, '0', STR_PAD_LEFT);
            $nextRunningNumber++;
            $attempts++;
        } while ($attempts < 1000 && $this->numberExists($type, $number));

        return $number;
    }

    /**
     * @return array{number: string, source: string}
     */
    public function resolve(string $type, mixed $submittedNumber, mixed $suggestedNumber = null): array
    {
        $submitted = $this->normalizeNumber($submittedNumber);
        $suggested = $this->normalizeNumber($suggestedNumber);

        if ($submitted === null || ($suggested !== null && $submitted === $suggested)) {
            return [
                'number' => $this->previewNext($type),
                'source' => 'auto',
            ];
        }

        return [
            'number' => $submitted,
            'source' => 'manual',
        ];
    }

    public function assertUnique(string $type, string $number, ?int $ignoreId = null): void
    {
        $config = $this->config($type);

        $query = DB::table($config['table'])
            ->where($config['column'], $number)
            ->when($ignoreId !== null, function ($query) use ($ignoreId) {
                $query->where('id', '<>', $ignoreId);
            });

        if ($this->hasSoftDeleteColumn($config['table'])) {
            $query->whereNull('deleted_at');
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                $config['field'] => $this->duplicateNumberMessage($type, $number, $ignoreId),
            ]);
        }
    }

    private function duplicateNumberMessage(string $type, string $number, ?int $ignoreId = null): string
    {
        $config = $this->config($type);
        $documentType = strtoupper($type);
        $label = match ($documentType) {
            'PO' => 'PO Number',
            'RR' => 'RR Number',
            'TS' => 'TS Number',
            'DR' => 'DR Number',
            default => $config['field'],
        };
        $fallbackMessage = "The {$label} {$number} has already been used.";
        $context = $this->duplicateNumberContext($documentType, $number, $ignoreId);

        if ($context === null) {
            return $fallbackMessage;
        }

        return "The {$label} {$number} has already been used by {$context}.";
    }

    private function duplicateNumberContext(string $type, string $number, ?int $ignoreId = null): ?string
    {
        return match ($type) {
            'PO' => $this->conflictSupplierName(
                table: 'purchase_orders',
                numberColumn: 'po_number',
                number: $number,
                ignoreId: $ignoreId,
            ),
            'RR' => $this->conflictRrSupplierName($number, $ignoreId),
            'TS' => $this->conflictTsSwsNumber($number, $ignoreId),
            'DR' => $this->conflictSupplierName(
                table: 'deliveries',
                numberColumn: 'dr_number',
                number: $number,
                ignoreId: $ignoreId,
            ),
            default => null,
        };
    }

    private function conflictSupplierName(string $table, string $numberColumn, string $number, ?int $ignoreId = null): ?string
    {
        $query = DB::table($table)
            ->leftJoin('suppliers', 'suppliers.id', '=', "{$table}.supplier_id")
            ->where("{$table}.{$numberColumn}", $number)
            ->when($ignoreId !== null, function ($query) use ($table, $ignoreId) {
                $query->where("{$table}.id", '<>', $ignoreId);
            });

        if ($this->hasSoftDeleteColumn($table)) {
            $query->whereNull("{$table}.deleted_at");
        }

        $supplierName = trim((string) $query->value('suppliers.name'));

        return $supplierName !== '' ? "supplier {$supplierName}" : null;
    }

    private function conflictRrSupplierName(string $number, ?int $ignoreId = null): ?string
    {
        $query = DB::table('receiving_reports')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'receiving_reports.purchase_order_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('receiving_reports.rr_number', $number)
            ->when($ignoreId !== null, function ($query) use ($ignoreId) {
                $query->where('receiving_reports.id', '<>', $ignoreId);
            });

        if ($this->hasSoftDeleteColumn('receiving_reports')) {
            $query->whereNull('receiving_reports.deleted_at');
        }

        $supplierName = trim((string) $query->value('suppliers.name'));

        return $supplierName !== '' ? "supplier {$supplierName}" : null;
    }

    private function conflictTsSwsNumber(string $number, ?int $ignoreId = null): ?string
    {
        $query = DB::table('transfer_slips')
            ->leftJoin('store_withdrawals', 'store_withdrawals.id', '=', 'transfer_slips.store_withdrawal_id')
            ->where('transfer_slips.ts_number', $number)
            ->when($ignoreId !== null, function ($query) use ($ignoreId) {
                $query->where('transfer_slips.id', '<>', $ignoreId);
            });

        if ($this->hasSoftDeleteColumn('transfer_slips')) {
            $query->whereNull('transfer_slips.deleted_at');
        }

        $swsNumber = trim((string) $query->value('store_withdrawals.sws_number'));

        return $swsNumber !== '' ? "SWS {$swsNumber}" : null;
    }

    public function isDuplicateNumberException(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $driverCode = (string) ($exception->errorInfo[1] ?? '');
        $message = strtolower($exception->getMessage());

        return in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['2601', '2627', '1062'], true)
            || str_contains($message, 'duplicate')
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'unique index');
    }

    /**
     * @return array{prefix: string, running_number: int, padding: int}
     */
    private function lastNumberParts(string $type): array
    {
        $config = $this->config($type);
        $best = [
            'prefix' => '',
            'running_number' => 0,
            'padding' => self::DEFAULT_PADDING,
        ];

        $query = DB::table($config['table'])
            ->whereNotNull($config['column']);

        if ($this->hasSoftDeleteColumn($config['table'])) {
            $query->whereNull('deleted_at');
        }

        $this->excludeReconcileAliasNumbers($type, $query, $config['table'], $config['column']);

        $lastNumber = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value($config['column']);

        $parts = $this->extractRunningNumber($lastNumber);
        if ($parts !== null) {
            return $parts;
        }

        return $best;
    }

    /**
     * @return array{prefix: string, running_number: int, padding: int}|null
     */
    private function extractRunningNumber(mixed $number): ?array
    {
        $normalized = $this->normalizeNumber($number);
        if ($normalized === null) {
            return null;
        }

        if (preg_match('/^(.*?)(\d+)$/', $normalized, $matches) !== 1) {
            return null;
        }

        return [
            'prefix' => $matches[1],
            'running_number' => (int) $matches[2],
            'padding' => strlen($matches[2]),
        ];
    }

    private function numberExists(string $type, string $number): bool
    {
        $config = $this->config($type);

        $query = DB::table($config['table'])
            ->where($config['column'], $number);

        if ($this->hasSoftDeleteColumn($config['table'])) {
            $query->whereNull('deleted_at');
        }

        return $query->exists();
    }

    private function excludeReconcileAliasNumbers(string $type, mixed $query, string $table, string $column): void
    {
        $documentType = strtolower(trim($type));

        if (Schema::hasTable('reconciliation_number_maps')) {
            $aliasNumbers = DB::table('reconciliation_number_maps')
                ->where('document_type', $documentType)
                ->where('resolution', 'import_as_alias')
                ->pluck('spfi_number')
                ->filter()
                ->values()
                ->all();

            if ($aliasNumbers !== []) {
                $query->whereNotIn($column, $aliasNumbers);
            }
        }

        if (Schema::hasColumn($table, 'meta')) {
            $query->where(function ($inner) use ($table): void {
                $inner->whereNull("{$table}.meta")
                    ->orWhere(function ($metaQuery) use ($table): void {
                        $metaQuery->where("{$table}.meta", 'not like', '%"aliased_from":"%')
                            ->orWhere("{$table}.meta", 'like', '%"aliased_from":null%')
                            ->orWhere("{$table}.meta", 'like', '%"aliased_from":""%');
                    });
            });
        }
    }

    private function hasSoftDeleteColumn(string $table): bool
    {
        static $cache = [];

        if (! array_key_exists($table, $cache)) {
            $cache[$table] = DB::getSchemaBuilder()->hasColumn($table, 'deleted_at');
        }

        return $cache[$table];
    }

    /**
     * @return array{table: string, column: string, field: string}
     */
    private function config(string $type): array
    {
        $type = strtoupper(trim($type));

        if (! isset(self::DOCUMENTS[$type])) {
            throw new \InvalidArgumentException("Unsupported document type [{$type}].");
        }

        return self::DOCUMENTS[$type];
    }

    private function normalizeNumber(mixed $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $normalized = trim((string) $number);

        return $normalized !== '' ? $normalized : null;
    }
}

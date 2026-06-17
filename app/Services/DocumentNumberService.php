<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
            $number = $last['prefix'] . str_pad((string) $nextRunningNumber, $padding, '0', STR_PAD_LEFT);
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

        $exists = DB::table($config['table'])
            ->where($config['column'], $number)
            ->when($ignoreId !== null, function ($query) use ($ignoreId) {
                $query->where('id', '<>', $ignoreId);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                $config['field'] => "The {$config['field']} has already been used.",
            ]);
        }
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

        return DB::table($config['table'])
            ->where($config['column'], $number)
            ->exists();
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

<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ResolvesLegacyDepartmentLookup
{
    /**
     * Legacy mapping reused from old PRS import logic.
     *
     * @var array<string, int>
     */
    private array $legacyDepartmentIdByCode = [
        '7056' => 1, '7054' => 2, '7000' => 3, '7010' => 4, '7029' => 5,
        '7030' => 6, '7031' => 7, '7032' => 8, '7033' => 9, '7034' => 10,
        '7035' => 11, '7036' => 12, '7037' => 13, '7038' => 14, '7039' => 15,
        '7040' => 16, '7042' => 17, '7044' => 18, '7046' => 19, '7048' => 20,
        '7050' => 21, '7052' => 22, '7060' => 23, '7061' => 24, '7062' => 25,
        '7063' => 26, '7064' => 27, '7033E' => 28, '7033C' => 29, '7033D' => 30,
        '7033F' => 31, '7033G' => 32, '8000' => 33, '001' => 34,
    ];

    /**
     * @var array<string, int>
     */
    private array $departmentIdByCode = [];

    protected function prepareLegacyDepartmentLookup(): void
    {
        $this->departmentIdByCode = [];

        $dbPairs = DB::table('departments')->pluck('id', 'code')->all();

        foreach ($dbPairs as $code => $id) {
            $normalized = $this->normalizeDepartmentLookupText((string) $code);
            if ($normalized === '') {
                continue;
            }

            if (! isset($this->departmentIdByCode[$normalized])) {
                $this->departmentIdByCode[$normalized] = (int) $id;
            }
        }

        foreach ($this->legacyDepartmentIdByCode as $code => $id) {
            $normalized = $this->normalizeDepartmentLookupText($code);

            if (! isset($this->departmentIdByCode[$normalized])) {
                $this->departmentIdByCode[$normalized] = (int) $id;
            }
        }
    }

    protected function resolveLegacyDepartmentId(mixed $rawDepartmentCode): ?int
    {
        $value = $this->normalizeDepartmentValue($rawDepartmentCode);

        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeDepartmentLookupText($value);

        if (isset($this->departmentIdByCode[$normalized])) {
            return $this->departmentIdByCode[$normalized];
        }

        $sorted = array_keys($this->departmentIdByCode);
        usort($sorted, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($sorted as $knownCode) {
            if (Str::startsWith($normalized, $knownCode)) {
                return $this->departmentIdByCode[$knownCode];
            }
        }

        return null;
    }

    private function normalizeDepartmentValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || strtoupper($normalized) === 'NULL') {
            return null;
        }

        return $normalized;
    }

    private function normalizeDepartmentLookupText(string $value): string
    {
        return strtolower(trim($value));
    }
}

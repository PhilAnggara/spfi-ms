<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;

trait ResolvesLegacyUserLookup
{
    /**
     * @var array<string, int>
     */
    private array $legacyUserIdByName = [];

    /**
     * @var array<string, int>
     */
    private array $legacyUserIdByUsername = [];

    /**
     * @var array<int, bool>
     */
    private array $legacyUserIds = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $legacyUserRowsById = [];

    /**
     * @param array<int, string> $additionalColumns
     */
    protected function prepareLegacyUserLookup(array $additionalColumns = []): void
    {
        $columns = array_values(array_unique(array_merge(['id', 'name', 'username'], $additionalColumns)));

        $users = DB::table('users')
            ->select($columns)
            ->orderBy('id')
            ->get();

        $this->legacyUserIdByName = [];
        $this->legacyUserIdByUsername = [];
        $this->legacyUserIds = [];
        $this->legacyUserRowsById = [];

        foreach ($users as $user) {
            $row = (array) $user;
            $id = (int) ($row['id'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $this->legacyUserIds[$id] = true;
            $this->legacyUserRowsById[$id] = $row;

            $name = $this->normalizeLegacyUserValue($row['name'] ?? null);
            if ($name !== null) {
                $key = $this->normalizeLegacyLookupText($name);
                if (! isset($this->legacyUserIdByName[$key])) {
                    $this->legacyUserIdByName[$key] = $id;
                }
            }

            $username = $this->normalizeLegacyUserValue($row['username'] ?? null);
            if ($username !== null) {
                $key = $this->normalizeLegacyLookupText($username);
                if (! isset($this->legacyUserIdByUsername[$key])) {
                    $this->legacyUserIdByUsername[$key] = $id;
                }
            }
        }
    }

    protected function resolveLegacyFallbackUserId(int $preferredId = 2): int
    {
        if (isset($this->legacyUserIds[$preferredId])) {
            return $preferredId;
        }

        $firstUserId = array_key_first($this->legacyUserIds);

        return $firstUserId !== null ? (int) $firstUserId : $preferredId;
    }

    protected function resolveLegacyUserId(
        mixed $rawUser,
        int $fallbackUserId,
        bool $nullableIfEmpty = false,
        bool $nullableIfUnmatched = false,
    ): ?int
    {
        $value = $this->normalizeLegacyUserValue($rawUser);

        if ($value === null) {
            return $nullableIfEmpty ? null : $fallbackUserId;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            if (isset($this->legacyUserIds[$id])) {
                return $id;
            }
        }

        $key = $this->normalizeLegacyLookupText($value);

        if (isset($this->legacyUserIdByUsername[$key])) {
            return $this->legacyUserIdByUsername[$key];
        }

        if (isset($this->legacyUserIdByName[$key])) {
            return $this->legacyUserIdByName[$key];
        }

        $matches = [];

        foreach ($this->legacyUserIdByName as $name => $id) {
            if (str_contains($name, $key) || str_contains($key, $name)) {
                $matches[$id] = true;
            }
        }

        if (count($matches) === 1) {
            return (int) array_key_first($matches);
        }

        return $nullableIfUnmatched ? null : $fallbackUserId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getLegacyUserLookupRowsById(): array
    {
        return $this->legacyUserRowsById;
    }

    private function normalizeLegacyUserValue(mixed $value): ?string
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

    private function normalizeLegacyLookupText(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? $value));
    }
}

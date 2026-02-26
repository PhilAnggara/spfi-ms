<?php

namespace Database\Seeders;

use App\Models\Grouping;
use App\Models\AccountingGroupCode;
use App\Models\AccountingCode;
use App\Models\BsGrouping;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingDataSeeder extends Seeder
{
    use ResolvesLegacyImport;

    // Import data accounting beserta relasi pivot-nya.
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->command->info('🔄 Importing Accounting Data...');

            // 1. Import Groupings
            $this->importGroupings();

            // 2. Import Group Codes
            $this->importGroupCodes();

            // 3. Import Accounting Codes
            $this->importAccountingCodes();

            // 4. Import BS Groupings (Pivot Table)
            $this->importBSGroupings();

            DB::commit();
            $this->command->info('✅ All data imported successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Import failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function importGroupings(): void
    {
        $rows = $this->rowsFromSource('acct_sub_group');
        $count = 0;

        foreach ($rows as $row) {
            $code = $this->readValue($row, ['code', 'subgroup_code', 'grouping_code'], 2);

            if (empty($code)) {
                continue;
            }

            Grouping::updateOrCreate(
                ['code' => $code],
                [
                    'desc' => $this->readValue($row, ['desc', 'description', 'subgroup_desc'], 5) ?? '',
                    'major' => $this->readValue($row, ['major'], 1),
                    'grp' => (int)($this->readValue($row, ['grp', 'group'], 3) ?? 0),
                    'tab' => (int)($this->readValue($row, ['tab'], 4) ?? 0),
                    'other' => $this->toBool($this->readValue($row, ['other'], 6)),
                    'selection' => $this->toBool($this->readValue($row, ['selection'], 8)),
                ]
            );

            $count++;
        }

        $this->command->info("✓ Imported {$count} Groupings");
    }

    private function importGroupCodes(): void
    {
        $rows = $this->rowsFromSource('acct_group_codes');
        $count = 0;

        foreach ($rows as $row) {
            $groupCode = $this->readValue($row, ['group_code', 'code'], 1);
            $groupDesc = $this->readValue($row, ['group_desc', 'desc', 'description'], 2) ?? '';

            if (empty($groupCode)) {
                continue;
            }

            AccountingGroupCode::updateOrCreate(
                ['group_code' => $groupCode],
                ['group_desc' => $groupDesc]
            );

            $count++;
        }

        $this->command->info("✓ Imported {$count} Group Codes");
    }

    private function importAccountingCodes(): void
    {
        $rows = $this->rowsFromSource('accounting_codes');
        $count = 0;

        foreach ($rows as $row) {
            $code = $this->readValue($row, ['code', 'acct_code', 'accounting_code'], 1);
            $desc = $this->readValue($row, ['desc', 'description', 'acct_desc'], 2) ?? '';

            if (empty($code)) {
                continue;
            }

            AccountingCode::updateOrCreate(
                ['code' => $code],
                ['desc' => $desc]
            );

            $count++;
        }

        $this->command->info("✓ Imported {$count} Accounting Codes");
    }

    private function importBSGroupings(): void
    {
        $rows = $this->rowsFromSource('bs_grouping');
        $count = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $groupCode = $this->readValue($row, ['group_code'], 1);
            $acctCode = $this->readValue($row, ['code', 'accounting_code', 'acct_code'], 2);
            $major = $this->readValue($row, ['major'], 3);
            $groupingCode = $this->readValue($row, ['grouping_code', 'subgroup_code'], 4);

            if (empty($groupCode) || empty($acctCode)) {
                continue;
            }

            $group = AccountingGroupCode::where('group_code', $groupCode)->first();
            $accountingCode = AccountingCode::where('code', $acctCode)->first();
            $grouping = $groupingCode ? Grouping::where('code', $groupingCode)->first() : null;

            if (!$group || !$accountingCode) {
                $skipped++;
                continue;
            }

            BsGrouping::updateOrCreate(
                [
                    'group_code_id' => $group->id,
                    'accounting_code_id' => $accountingCode->id,
                ],
                [
                    'grouping_id' => $grouping?->id,
                    'major' => $major,
                ]
            );

            $count++;
        }

        $this->command->info("✓ Imported {$count} BS Groupings");

        if ($skipped > 0) {
            $this->command->warn("⚠ Skipped {$skipped} records (missing references)");
        }
    }

    private function rowsFromSource(string $dataset): array
    {
        $legacyRows = $this->resolveRows($dataset, fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->logImportSource($dataset, 'legacy');
            $this->command?->info("ℹ [{$dataset}] rows loaded: " . count($legacyRows));
            return $legacyRows;
        }

        $this->logImportSource($dataset, 'csv');

        $csvFile = $this->csvPathFor($dataset);

        if (!file_exists($csvFile)) {
            $this->command?->warn("⚠ File not found: {$csvFile}");
            return [];
        }

        $rows = [];
        if (($handle = fopen($csvFile, 'r')) === false) {
            $this->command?->warn("⚠ Failed to open file: {$csvFile}");
            return [];
        }

        fgetcsv($handle, 0, ';');

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        $this->command?->info("ℹ [{$dataset}] rows loaded: " . count($rows));

        return $rows;
    }

    private function readValue(array $row, array $keys, ?int $index = null): ?string
    {
        $keyLookup = [];
        foreach ($row as $k => $v) {
            if (!is_string($k)) {
                continue;
            }

            $lower = strtolower($k);
            $compact = preg_replace('/[^a-z0-9]/', '', $lower) ?? $lower;

            $keyLookup[$lower] = $v;
            $keyLookup[$compact] = $v;
        }

        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                $value = trim((string) ($row[$key] ?? ''));
                return $value === '' ? null : $value;
            }

            $lowerKey = strtolower($key);
            $compactKey = preg_replace('/[^a-z0-9]/', '', $lowerKey) ?? $lowerKey;

            if (array_key_exists($lowerKey, $keyLookup)) {
                $value = trim((string) ($keyLookup[$lowerKey] ?? ''));
                return $value === '' ? null : $value;
            }

            if (array_key_exists($compactKey, $keyLookup)) {
                $value = trim((string) ($keyLookup[$compactKey] ?? ''));
                return $value === '' ? null : $value;
            }
        }

        if ($index !== null && array_key_exists($index, $row)) {
            $value = trim((string) ($row[$index] ?? ''));
            return $value === '' ? null : $value;
        }

        if ($index !== null) {
            $values = array_values($row);
            if (array_key_exists($index, $values)) {
                $value = trim((string) ($values[$index] ?? ''));
                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    private function toBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtoupper(trim($value));

        return in_array($normalized, ['1', 'Y', 'YES', 'TRUE', 'T'], true);
    }
}

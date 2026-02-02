<?php

namespace Database\Seeders;

use App\Models\Grouping;
use App\Models\AccountingGroupCode;
use App\Models\AccountingCode;
use App\Models\BsGrouping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingDataSeeder extends Seeder
{
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
        $csvFile = public_path('document/csv/[tbl_Acct_SubGroup].csv');

        if (!file_exists($csvFile)) {
            $this->command->warn("⚠ File not found: {$csvFile}");
            return;
        }

        $count = 0;

        if (($handle = fopen($csvFile, 'r')) !== false) {
            fgetcsv($handle, 0, ';'); // Skip header

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $code = trim($row[2] ?? '');

                if (empty($code)) {
                    continue;
                }

                Grouping::updateOrCreate(
                    ['code' => $code],
                    [
                        'desc' => trim($row[5] ?? ''),
                        'major' => trim($row[1] ?? '') ?: null,
                        'grp' => (int)($row[3] ?? 0),
                        'tab' => (int)($row[4] ?? 0),
                        'other' => (bool)($row[6] ?? false),
                        'selection' => (bool)($row[8] ?? false),
                    ]
                );

                $count++;
            }
            fclose($handle);
        }

        $this->command->info("✓ Imported {$count} Groupings");
    }

    private function importGroupCodes(): void
    {
        $csvFile = public_path('document/csv/[tbl_Acct_GroupCodes].csv');

        if (!file_exists($csvFile)) {
            $this->command->warn("⚠ File not found: {$csvFile}");
            return;
        }

        $count = 0;

        if (($handle = fopen($csvFile, 'r')) !== false) {
            fgetcsv($handle, 0, ';'); // Skip header

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $groupCode = trim($row[1] ?? '');
                $groupDesc = trim($row[2] ?? '');

                // Skip jika code kosong
                if (empty($groupCode)) {
                    continue;
                }

                AccountingGroupCode::updateOrCreate(
                    ['group_code' => $groupCode],
                    ['group_desc' => $groupDesc]
                );

                $count++;
            }
            fclose($handle);
        }

        $this->command->info("✓ Imported {$count} Group Codes");
    }

    private function importAccountingCodes(): void
    {
        $csvFile = public_path('document/csv/[tbl_AccountingCodes].csv');

        if (!file_exists($csvFile)) {
            $this->command->warn("⚠ File not found: {$csvFile}");
            return;
        }

        $count = 0;

        if (($handle = fopen($csvFile, 'r')) !== false) {
            fgetcsv($handle, 0, ';'); // Skip header

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $code = trim($row[1] ?? '');
                $desc = trim($row[2] ?? '');

                // Skip jika code kosong
                if (empty($code)) {
                    continue;
                }

                AccountingCode::updateOrCreate(
                    ['code' => $code],
                    ['desc' => $desc]
                );

                $count++;
            }
            fclose($handle);
        }

        $this->command->info("✓ Imported {$count} Accounting Codes");
    }

    private function importBSGroupings(): void
    {
        $csvFile = public_path('document/csv/[tbl_BSGrouping].csv');

        if (!file_exists($csvFile)) {
            $this->command->warn("⚠ File not found: {$csvFile}");
            return;
        }

        $count = 0;
        $skipped = 0;

        if (($handle = fopen($csvFile, 'r')) !== false) {
            fgetcsv($handle, 0, ';'); // Skip header

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $groupCode = trim($row[1] ?? '');
                $acctCode = trim($row[2] ?? '');
                $major = trim($row[3] ?? '') ?: null;
                $groupingCode = trim($row[4] ?? '') ?: null;

                // Skip jika kosong
                if (empty($groupCode) || empty($acctCode)) {
                    continue;
                }

                // Cari foreign keys - HANYA gunakan yang ada di database
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
            fclose($handle);
        }

        $this->command->info("✓ Imported {$count} BS Groupings");

        if ($skipped > 0) {
            $this->command->warn("⚠ Skipped {$skipped} records (missing references)");
        }
    }
}

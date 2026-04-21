<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyDepartmentLookup;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PrsSeeder extends Seeder
{
    use ResolvesLegacyDepartmentLookup;
    use ResolvesLegacyImport;
    use ResolvesLegacyUserLookup;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Coba ambil data legacy jika mode seeding = legacy.
        $legacyRows = $this->resolveRows('prs', fn (string $message) => $this->command?->warn($message));

        if ($this->isLegacySource() && !empty($legacyRows)) {
            $this->seedRows($legacyRows, 'legacy');
            return;
        }

        $this->seedFromCsv();
    }

    /**
     * Seed PRS dari source impor ter-normalisasi.
     */
    protected function seedRows(array $rows, string $source): void
    {
        $this->logImportSource('prs', $source);
        $this->command?->info("ℹ [prs] rows loaded: " . count($rows));

        $this->prepareLegacyUserLookup();
        $this->prepareLegacyDepartmentLookup();
        $defaultUserId = $this->resolveLegacyFallbackUserId(2);

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $data) {
            $prsNumber = trim((string) ($data['prsnumber'] ?? ''));

            if ($prsNumber === '') {
                $this->warn("PRS skipped: missing prsnumber");
                $skipped++;
                continue;
            }

            // --- resolve department_id ---
            $departmentCode = trim((string) ($data['department_name'] ?? ''));
            $departmentId = $this->resolveLegacyDepartmentId($departmentCode);

            if ($departmentId === null) {
                $this->warn("PRS skipped: department_name '{$departmentCode}' not found in mapping (prsnumber: {$prsNumber})");
                $skipped++;
                continue;
            }

            // --- resolve user_id ---
            $createdBy = trim((string) ($data['createdby'] ?? ''));
            $userId = $this->resolveLegacyUserId($createdBy, $defaultUserId) ?? $defaultUserId;

            // --- parse dates ---
            $createdDate = $this->parseDate($data['created_date'] ?? null);
            $updatedDate = $this->parseDate($data['updated_date'] ?? null);
            $prsDate = $this->parseDate($data['prsdate'] ?? null) ?? now();
            $requestDate = $this->parseDate($data['requestdate'] ?? null) ?? now();

            // --- soft delete ---
            $isActive = strtoupper(trim((string) ($data['is_active'] ?? 'Y'))) === 'Y';
            $deletedAt = $isActive ? null : $updatedDate;

            DB::table('prs')->updateOrInsert(
                ['prs_number' => $prsNumber],
                [
                    'prs_number' => $prsNumber,
                    'user_id' => $userId,
                    'department_id' => $departmentId,
                    'prs_date' => $prsDate,
                    'date_needed' => $requestDate,
                    'remarks' => $data['remarks'] ?? null,
                    'status' => 'REQUESTED',
                    'created_at' => $createdDate ?? now(),
                    'updated_at' => $updatedDate ?? now(),
                    'deleted_at' => $deletedAt,
                ]
            );

            $inserted++;
        }

        $this->command?->info("✓ [prs] Inserted/Updated: {$inserted}, Skipped: {$skipped}");
    }

    /**
     * Seed PRS dari CSV lokal.
     */
    protected function seedFromCsv(): void
    {
        $csvPath = $this->csvPathFor('prs');

        if (!File::exists($csvPath)) {
            $this->warn("prs.csv not found at: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');
        if ($handle === false) {
            $this->warn("Failed to open prs.csv at: {$csvPath}");
            return;
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);
            $this->warn("prs.csv is empty: {$csvPath}");
            return;
        }

        $rows = [];
        $skippedInvalidColumns = 0;

        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) !== count($header)) {
                $skippedInvalidColumns++;
                continue;
            }

            $data = array_combine($header, $row);
            if ($data === false) {
                $skippedInvalidColumns++;
                continue;
            }

            $rows[] = $data;
        }

        fclose($handle);

        if ($skippedInvalidColumns > 0) {
            $this->warn("PRS CSV skipped rows with invalid columns: {$skippedInvalidColumns}");
        }

        if (empty($rows)) {
            $this->warn('No CSV prs rows loaded.');
            return;
        }

        $this->seedRows($rows, 'csv');
    }

    // ─── helpers ────────────────────────────────────────────────────────

    protected function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '' || strtoupper(trim((string) $value)) === 'NULL') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    protected function warn(string $message): void
    {
        $this->command?->warn("⚠ {$message}");
        Log::warning("[PrsSeeder] {$message}");
    }
}

<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyDepartmentLookup;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Database\Seeders\Concerns\ResolvesLegacyUserLookup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
            $this->seedFromLegacy($legacyRows);
            return;
        }

        // Fallback ke local seeder (data manual yang sudah ada).
        $this->seedLocal();
    }

    /**
     * Seed PRS dari legacy database.
     */
    protected function seedFromLegacy(array $legacyRows): void
    {
        $this->logImportSource('prs', 'legacy');
        $this->command?->info("ℹ [prs] rows loaded: " . count($legacyRows));

        $this->prepareLegacyUserLookup();
        $this->prepareLegacyDepartmentLookup();
        $defaultUserId = $this->resolveLegacyFallbackUserId(2);

        $inserted = 0;
        $skipped = 0;

        foreach ($legacyRows as $data) {
            $prsNumber = trim((string) ($data['prsnumber'] ?? ''));

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
                    'status' => 'DELIVERY_COMPLETE',
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
     * Seed PRS dari local seeder (data manual yang sudah ada sebelumnya).
     */
    protected function seedLocal(): void
    {
        $this->logImportSource('prs', 'local');

        DB::table('prs')->insert([
            [
                'prs_number' => '7056-010126-001',
                'user_id' => 1,
                'department_id' => 1,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(5),
                'remarks' => null,
                'status' => 'SUBMITTED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_number' => '7056-010126-002',
                'user_id' => 1,
                'department_id' => 1,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(5),
                'remarks' => null,
                'status' => 'SUBMITTED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_number' => '7050-010126-003',
                'user_id' => 5,
                'department_id' => 5,
                'prs_date' => Carbon::now(),
                'date_needed' => Carbon::now()->addDays(2),
                'remarks' => 'Penting',
                'status' => 'SUBMITTED',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
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

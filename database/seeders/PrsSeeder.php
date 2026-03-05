<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PrsSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Mapping department code -> id.
     */
    protected array $departmentIdByCode = [
        '7056' => 1, '7054' => 2, '7000' => 3, '7010' => 4, '7029' => 5,
        '7030' => 6, '7031' => 7, '7032' => 8, '7033' => 9, '7034' => 10,
        '7035' => 11, '7036' => 12, '7037' => 13, '7038' => 14, '7039' => 15,
        '7040' => 16, '7042' => 17, '7044' => 18, '7046' => 19, '7048' => 20,
        '7050' => 21, '7052' => 22, '7060' => 23, '7061' => 24, '7062' => 25,
        '7063' => 26, '7064' => 27, '7033E' => 28, '7033C' => 29, '7033D' => 30,
        '7033F' => 31, '7033G' => 32, '8000' => 33, '001' => 34,
    ];

    /**
     * Mapping user name -> id (first occurrence wins for duplicates).
     * Duplicate keys in PHP arrays are overwritten, so duplicates are commented
     * and the first id is kept intentionally.
     */
    protected array $userIdByName = [
        'Phil Bawole' => 1,
        'System Administrator' => 2,
        'SPFI IT Division' => 3,
        'Fish Staff for Test' => 4,
        'James' => 5,
        'Staff Purchasing' => 6,
        'Denny Tuhatelu' => 7,
        'Jeffry Lantang' => 8,
        'Erni Ending' => 9,
        'Rommy Tendean' => 10,
        'Ivonne Peleh' => 11,
        'Swingly Boham' => 12,
        'Irwan' => 13,
        'Jellyta' => 14,
        'Wiske' => 15,
        'Daniel Watuna' => 16,
        'Ferdie Tobangen' => 17,
        'Wasis Wiyono' => 18,
        'Greity Selaindoong' => 19,
        'Max Pangkey' => 20,
        'Wensi Saranggi' => 21,  // duplicate name exists (id=27), first wins
        'Viven Pungus' => 22,
        'Rommy Makagiansar' => 23,
        'Venensi Lumempouw' => 24,
        'S.C. Calamba, Jr' => 25,
        'Taufik Ramadhani' => 26,
        'Rudy Mandagie' => 28,
        'Eduard Luas' => 29,     // duplicate name exists (id=50), first wins
        'Budi Satriyo' => 30,
        'James Runtukahu' => 31, // duplicate name exists (id=33), first wins
        'Dewi Ria' => 32,
        'Stainly Langkay' => 34,
        'Evita Patanduk' => 35,
        'Rizal Sinadia' => 36,
        'Yongki Lahea' => 37,
        'sherly tatontos' => 38,
        'Rendy Patandung' => 39,
        'Garry' => 40,
        'Rostefince Saberatu' => 41,
        'Johanes Tahulending' => 42,
        'testfish' => 43,
        'ITDept' => 44,
        'spgTv' => 45,
        'Angelika Angkow' => 46,
        'Bea Cukai' => 47,
        'Jefny Jacobus' => 48,
        'Sherly Tatontos' => 49,
        'Surya Kumakaw' => 51,
        'Anis Usman' => 52,
    ];

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

        $inserted = 0;
        $skipped = 0;

        foreach ($legacyRows as $data) {
            $prsNumber = trim((string) ($data['prsnumber'] ?? ''));

            // --- resolve department_id ---
            $departmentCode = trim((string) ($data['department_name'] ?? ''));
            $departmentId = $this->resolveDepartmentId($departmentCode);

            if ($departmentId === null) {
                $this->warn("PRS skipped: department_name '{$departmentCode}' not found in mapping (prsnumber: {$prsNumber})");
                $skipped++;
                continue;
            }

            // --- resolve user_id ---
            $createdBy = trim((string) ($data['createdby'] ?? ''));
            $userId = $this->resolveUserId($createdBy, $prsNumber);

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

    /**
     * Resolve department ID from code. Tries exact match first, then prefix match.
     */
    protected function resolveDepartmentId(string $code): ?int
    {
        if (isset($this->departmentIdByCode[$code])) {
            return $this->departmentIdByCode[$code];
        }

        // Prefix fallback: sort longest-first so '7033E' is tried before '7033'.
        $sorted = $this->departmentIdByCode;
        uksort($sorted, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($sorted as $knownCode => $id) {
            if (Str::startsWith($code, $knownCode)) {
                $this->warn("Department '{$code}' matched via prefix '{$knownCode}' (id={$id})");
                return $id;
            }
        }

        return null;
    }

    /**
     * Resolve user ID from name. Falls back to System Administrator (id=2).
     */
    protected function resolveUserId(string $name, string $context): int
    {
        $defaultUserId = 2; // System Administrator

        if ($name === '') {
            $this->warn("User name empty for PRS '{$context}', defaulting to System Administrator (id={$defaultUserId})");
            return $defaultUserId;
        }

        // Case-insensitive exact match
        foreach ($this->userIdByName as $userName => $id) {
            if (strcasecmp($userName, $name) === 0) {
                return $id;
            }
        }

        $this->warn("User '{$name}' not found for PRS '{$context}', defaulting to System Administrator (id={$defaultUserId})");
        return $defaultUserId;
    }

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

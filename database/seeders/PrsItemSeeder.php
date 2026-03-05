<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Database\Seeders\Concerns\ResolvesLegacyImport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrsItemSeeder extends Seeder
{
    use ResolvesLegacyImport;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->isLegacySource()) {
            $this->seedFromLegacy();
            return;
        }

        // Fallback ke local seeder (data manual yang sudah ada).
        $this->seedLocal();
    }

    /**
     * Seed PRS Items dari legacy database (prs_detail).
     */
    protected function seedFromLegacy(): void
    {
        $this->logImportSource('prs_detail', 'legacy');

        // Build lookup maps
        $prsIdByNumber = DB::table('prs')->pluck('id', 'prs_number')->all();
        $itemIdByCode = DB::table('items')->pluck('id', 'code')->all();

        if (empty($prsIdByNumber)) {
            $this->warn("No PRS records found in new DB. Make sure PrsSeeder ran first.");
            return;
        }

        // Ambil semua baris legacy sekaligus (chunk tidak kompatibel dgn SQL Server lama).
        $legacyRows = $this->resolveRows('prs_detail', fn (string $message) => $this->command?->warn($message));

        if (empty($legacyRows)) {
            $this->warn("No legacy prs_detail rows loaded.");
            return;
        }

        $this->command?->info("ℹ [prs_detail] rows loaded: " . count($legacyRows));

        $inserted = 0;
        $skipped = 0;

        foreach ($legacyRows as $data) {
            $prsNumber = trim((string) ($data['prsnumber'] ?? ''));
            $productCode = trim((string) ($data['productcode'] ?? ''));

            // Lookup prs_id
            $prsId = $prsIdByNumber[$prsNumber] ?? null;
            if ($prsId === null) {
                $this->warn("PRS Item skipped: prsnumber '{$prsNumber}' not found in prs table");
                $skipped++;
                continue;
            }

            // Lookup item_id
            $itemId = $itemIdByCode[$productCode] ?? null;
            if ($itemId === null) {
                $this->warn("PRS Item skipped: productcode '{$productCode}' not found in items table (prsnumber: {$prsNumber})");
                $skipped++;
                continue;
            }

            $createdDate = $this->parseDate($data['created_date'] ?? null);
            $updatedDate = $this->parseDate($data['updated_date'] ?? null);

            $isActive = strtoupper(trim((string) ($data['is_active'] ?? 'Y'))) === 'Y';
            $deletedAt = $isActive ? null : $updatedDate;

            $quantity = (int) ($data['qty'] ?? 0);

            // Upsert berdasarkan prs_id + item_id agar idempotent.
            DB::table('prs_items')->updateOrInsert(
                [
                    'prs_id' => $prsId,
                    'item_id' => $itemId,
                ],
                [
                    'prs_id' => $prsId,
                    'item_id' => $itemId,
                    'canvaser_id' => null,
                    'quantity' => $quantity,
                    'purchase_order_id' => null,
                    'selected_canvasing_item_id' => null,
                    'selection_reason' => null,
                    'is_direct_purchase' => false,
                    'created_at' => $createdDate ?? now(),
                    'updated_at' => $updatedDate ?? now(),
                    'deleted_at' => $deletedAt,
                ]
            );

            $inserted++;
        }

        $this->command?->info("✓ [prs_detail] Inserted/Updated: {$inserted}, Skipped: {$skipped}");
    }

    /**
     * Seed PRS Items dari local seeder (data manual yang sudah ada sebelumnya).
     */
    protected function seedLocal(): void
    {
        $this->logImportSource('prs_detail', 'local');

        DB::table('prs_items')->insert([
            [
                'prs_id' => 1,
                'item_id' => 1,
                'quantity' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 1,
                'item_id' => 2,
                'quantity' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 2,
                'item_id' => 3,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 2,
                'item_id' => 4,
                'quantity' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'prs_id' => 3,
                'item_id' => 1,
                'quantity' => 1,
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
        Log::warning("[PrsItemSeeder] {$message}");
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('non_fg_count_tags')) {
            return;
        }

        $this->migrateLegacyIdsIntoMeta();
        $this->swapAppCreatedDates();
        $this->normalizeCountTagDatesToMonthEnd();

        Schema::table('non_fg_count_tags', function (Blueprint $table) {
            if (Schema::hasColumn('non_fg_count_tags', 'legacy_id')) {
                $table->dropUnique(['legacy_id']);
                $table->dropColumn('legacy_id');
            }

            if (Schema::hasIndex('non_fg_count_tags', 'non_fg_ct_item_tran_idx')) {
                $table->dropIndex('non_fg_ct_item_tran_idx');
            }

            if (! Schema::hasIndex('non_fg_count_tags', 'non_fg_ct_item_period_idx')) {
                $table->index(['item_code', 'count_tag_date'], 'non_fg_ct_item_period_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('non_fg_count_tags')) {
            return;
        }

        Schema::table('non_fg_count_tags', function (Blueprint $table) {
            if (Schema::hasIndex('non_fg_count_tags', 'non_fg_ct_item_period_idx')) {
                $table->dropIndex('non_fg_ct_item_period_idx');
            }

            if (! Schema::hasIndex('non_fg_count_tags', 'non_fg_ct_item_tran_idx')) {
                $table->index(['item_code', 'tran_date'], 'non_fg_ct_item_tran_idx');
            }

            if (! Schema::hasColumn('non_fg_count_tags', 'legacy_id')) {
                $table->unsignedBigInteger('legacy_id')->nullable()->unique();
            }
        });

        $this->restoreLegacyIdsFromMeta();
    }

    private function migrateLegacyIdsIntoMeta(): void
    {
        if (! Schema::hasColumn('non_fg_count_tags', 'legacy_id')) {
            return;
        }

        DB::table('non_fg_count_tags')
            ->whereNotNull('legacy_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = $this->decodeMeta($row->meta);
                    $meta['legacy_id'] = (int) $row->legacy_id;

                    DB::table('non_fg_count_tags')
                        ->where('id', $row->id)
                        ->update(['meta' => json_encode($meta)]);
                }
            });
    }

    private function swapAppCreatedDates(): void
    {
        $hasLegacyId = Schema::hasColumn('non_fg_count_tags', 'legacy_id');

        DB::table('non_fg_count_tags')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($hasLegacyId): void {
                foreach ($rows as $row) {
                    $meta = $this->decodeMeta($row->meta);
                    $legacyId = $hasLegacyId ? ($row->legacy_id ?? null) : ($meta['legacy_id'] ?? null);
                    $isAppCreated = $legacyId === null || (($meta['source'] ?? null) === 'scan');

                    if (! $isAppCreated) {
                        continue;
                    }

                    DB::table('non_fg_count_tags')
                        ->where('id', $row->id)
                        ->update([
                            'count_tag_date' => $row->tran_date,
                            'tran_date' => $row->count_tag_date,
                        ]);
                }
            });
    }

    private function normalizeCountTagDatesToMonthEnd(): void
    {
        DB::table('non_fg_count_tags')
            ->whereNotNull('count_tag_date')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    try {
                        $endOfMonth = Carbon::parse($row->count_tag_date)->endOfMonth()->toDateString();
                    } catch (\Throwable) {
                        continue;
                    }

                    if ($endOfMonth === (string) $row->count_tag_date) {
                        continue;
                    }

                    DB::table('non_fg_count_tags')
                        ->where('id', $row->id)
                        ->update(['count_tag_date' => $endOfMonth]);
                }
            });
    }

    private function restoreLegacyIdsFromMeta(): void
    {
        DB::table('non_fg_count_tags')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = $this->decodeMeta($row->meta);
                    $legacyId = $meta['legacy_id'] ?? null;

                    if ($legacyId === null) {
                        continue;
                    }

                    DB::table('non_fg_count_tags')
                        ->where('id', $row->id)
                        ->update(['legacy_id' => (int) $legacyId]);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if ($meta === null || $meta === '') {
            return [];
        }

        $decoded = json_decode((string) $meta, true);

        return is_array($decoded) ? $decoded : [];
    }
};

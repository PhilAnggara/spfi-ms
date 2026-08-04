# Dual-system reconciliation (IMS → spfi_ms)

Incremental sync from legacy IMS (`legacy_sqlsrv_1` / `b12d4a36`) into SPFI-MS (`spfi_ms`).

**Konteks:** SPFI-MS sudah dipakai sebagai data **production** (bukan sekadar trial). Tujuan sync adalah **melengkapi** SPFI-MS dengan transaksi/master yang hanya ada di IMS, **tanpa menghapus** data production yang sudah ada di SPFI-MS.

## Before you start

1. **Backup `spfi_ms`** (SQL Server `.bak` atau MySQL dump). Wajib.
2. Jadwalkan jendela tenang: set `RECONCILE_FREEZE_WRITES=true` di `.env`, lalu `php artisan config:clear`.
3. Pastikan jaringan kantor bisa mengakses IMS (`LEGACY_DB1_*` / `legacy_sqlsrv_1`).
4. Informasikan user: selama freeze, create/edit PRS–TS di SPFI-MS diblokir.

## Commands

### Report only (audit, tidak mengubah data)

```bash
php artisan reconcile:ims-to-new --report --since=2026-07-15
```

Filter opsional:

```bash
php artisan reconcile:ims-to-new --report --only=prs,po,rr,sws,ts,stock
```

Output CSV + `summary.json` di `storage/app/reconcile-reports/{timestamp}/`:

- `{dataset}_ims_only.csv` — ada di IMS, belum di SPFI-MS (**target import**)
- `{dataset}_new_only.csv` — hanya di SPFI-MS (**dipertahankan**)
- `{dataset}_content_mismatches.csv` — nomor sama, isi beda
- `stock_mismatches.csv`

### Apply import

```bash
php artisan reconcile:ims-to-new --apply --since=2026-07-15
```

| Flag | Meaning |
|------|---------|
| `--conflict=import-as-alias` | Default: pertahankan baris SPFI-MS; salin versi IMS dengan nomor alias + map |
| `--conflict=skip` | Laporkan mismatch, jangan import salinan IMS yang bentrok |
| `--no-stock` | Lewati posting `StockService` untuk RR/TS yang baru diimpor |
| `--only=` | Batasi dataset |

Urutan import: supplier → product → PRS → canvassing → PO → RR → SWS → TS → DR, lalu stok untuk RR/TS yang diimpor.

## Conflict policy (nomor sama, isi berbeda)

Karena **kedua sisi production**:

- Dokumen SPFI-MS yang sudah ada **tidak ditimpa**.
- Versi IMS diimpor dengan **nomor alias** unik.
- Mapping di `reconciliation_number_maps` (`ims_number` → `spfi_number`).
- Jejak insert/skip/error di `reconciliation_import_logs`.

## Freeze banner

Dengan `RECONCILE_FREEZE_WRITES=true`, route mutate PRS / PO / RR / SWS / TS / deliveries / canvassing di-block (503 JSON atau redirect + flash). Banner peringatan muncul di layout.

Setelah sync selesai:

```env
RECONCILE_FREEZE_WRITES=false
```

```bash
php artisan config:clear
```

## Verify after apply

1. Ulangi `--report` — `ims_only` untuk dokumen transaksi mendekati 0.
2. Cek `reconciliation_number_maps` untuk SWS/TS yang di-alias.
3. Review `stock_mismatches.csv` — beda stok masih mungkin jika ada pergerakan yang hanya ada di satu sistem.
4. Pastikan dokumen/master yang hanya ada di SPFI-MS tetap utuh.

### Catatan perbaikan impor

- User opsional (approved_by/noted_by kosong) tidak lagi membuat import gagal.
- Lookup nomor/UOM case-insensitive.
- Parent PO/SWS diimpor otomatis bila dibutuhkan RR/TS.
- Dokumen yang **soft-deleted** di SPFI-MS tapi masih ada di IMS akan **di-restore** (bukan gagal duplicate key).

## Stock rebuild sejak 15 Juli (RR / TS / DR)

Jika ledger stok partial/salah setelah sync, hitung ulang dampak dokumen aktif di jendela tanggal lewat `StockService` (bukan overwrite dari `stock_inventory` IMS). Opening sebelum `--from` tetap dari sisa ledger setelah purge.

`--rebuild` **menghapus** baris `stock_balances` RR/TS/DR di jendela (termasuk dokumen alias reconcile), mengembalikan efek net-nya ke `stock_inventory`, lalu **mem-post ulang hanya dokumen non-alias** secara kronologis. Ini mencegah double-post dari:

- TS/RR alias (`import_as_alias`, nomor seperti `011xxx`) yang isinya duplikat dokumen SPFI yang sudah ada
- rebuild berulang yang menumpuk baris reverse/replay

```bash
# Dry-run — daftar dokumen yang akan di-purge lalu di-replay
php artisan stock:backfill-current-month --from=2026-07-15 --rebuild --dry-run

# Apply (backup spfi_ms dulu; --force mengizinkan saldo sementara negatif saat replay)
php artisan stock:backfill-current-month --from=2026-07-15 --rebuild --force
```

Tanpa `--rebuild`, command hanya mengisi posting yang belum ada (`hasPostedReference` = false). Tanpa `--from`, jendela tetap bulan kalender dari `--date` (default: hari ini). Impor reconcile **tidak** mem-post stok untuk dokumen alias.

## Related config

- [`config/reconcile.php`](../config/reconcile.php)
- [`config/legacy_import.php`](../config/legacy_import.php)
- Mapping: [`legacy-po-mapping.md`](legacy-po-mapping.md), [`legacy-rr-stock-mapping.md`](legacy-rr-stock-mapping.md)

# Legacy PO Mapping (PO + PO Detail)

Dokumen ini merangkum mapping dari sistem lama ke sistem baru untuk alur:
`PRS -> Purchase Order -> Approval -> Receiving Report`.

## Ringkasan Alur Bisnis Baru

1. PRS dibuat oleh requester (`prs`, `prs_items`).
2. Purchasing melakukan canvasing dan memilih supplier per `prs_item`.
3. PO dibuat dari item yang dipilih, bisa `DRAFT` atau `PENDING_APPROVAL`.
4. Manager/GM melakukan approval -> status `APPROVED`.
5. Inventory membuat RR dari PO approved, stok dan riwayat stok diperbarui.

Implikasi untuk import legacy:
- PO historis yang sudah approved harus masuk sebagai `APPROVED`.
- PO historis yang baru certified dimasukkan sebagai `PENDING_APPROVAL`.
- Relasi `prs_item_id` di `purchase_order_items` diusahakan terhubung dari `prsnumber + product_code (+ department_code)`.

## Mapping Header PO

Sumber: `po` (legacy) -> `purchase_orders` (baru)

| Legacy (`po`) | New (`purchase_orders`) | Aturan Mapping |
|---|---|---|
| `po_code` | `po_number` | langsung, unique |
| `supplier_code` | `supplier_id` | lookup ke `suppliers.code` |
| `created_by` | `created_by` | lookup ke `users` (name/username/id), fallback default user |
| `is_approved`, `is_certified` | `status` | `APPROVED` jika approved, `PENDING_APPROVAL` jika certified, selain itu `DRAFT` |
| `po_date` | `submitted_at` | parse tanggal |
| `approved_date` | `approved_at` | parse tanggal |
| `certified_by` | `certified_by_user_id` | lookup user (nullable) |
| `approved_by` | `approved_by_user_id` | lookup user (nullable) |
| `currency` | `currency_id` | lookup `currencies.code`, fallback by name, lalu default currency |
| `discount` | `discount_rate` | persen |
| `ppn` | `ppn_rate` | persen |
| `pph` | `pph_rate` | persen |
| hasil hitung detail | `subtotal` | sum `line_subtotal` detail aktif |
| hasil hitung detail | `discount_amount` | sum `discount_amount` detail |
| hasil hitung detail | `ppn_amount` | sum `ppn_amount` detail |
| hasil hitung detail | `pph_amount` | sum `pph_amount` detail |
| fixed | `tax_rate`, `tax_amount` | `0` (tidak dipakai di flow saat ini) |
| fixed | `fees` | `0` (legacy tidak menyediakan field biaya terpisah) |
| hasil hitung | `total` | `subtotal - discount_amount + ppn_amount - pph_amount + fees` |
| `remarks` | `remark_text` | langsung |
| `remarks` | `remark_type` | `Confirmatory` jika teks mengandung "confirm", selain itu `Normal` |
| `created_date` | `created_at` | parse tanggal |
| `updated_date` | `updated_at` | parse tanggal |
| `is_active` | `deleted_at` | `NULL` jika aktif, jika non-aktif gunakan `updated_at` |
| `rate_id`, `is_BC`, metadata approval | `signature_meta` | disimpan sebagai jejak legacy |

## Mapping Detail PO

Sumber: `po_detail` (legacy) -> `purchase_order_items` (baru)

| Legacy (`po_detail`) | New (`purchase_order_items`) | Aturan Mapping |
|---|---|---|
| `id` | `id` | dipertahankan jika tersedia |
| `po_code` | `purchase_order_id` | lookup ke `purchase_orders.po_number` |
| `product_code` | `item_id` | lookup ke `items.code` |
| `prsnumber` (+ `product_code`, `department_code`) | `prs_item_id` | lookup kandidat ke `prs_items` via relasi `prs.prs_number` dan `item_id` |
| `sub_total` | `line_subtotal` | langsung (decimal) |
| `sub_total` + quantity referensi | `unit_price` | `line_subtotal / quantity` |
| quantity PRS item | `quantity` | dari `prs_items.quantity` jika ketemu, fallback `1` |
| hitung | `discount_rate` | dari header PO (`discount`) |
| hitung | `discount_amount` | `line_subtotal * (discount_rate / 100)` |
| hitung | `ppn_rate` | dari header PO (`ppn`) |
| hitung | `ppn_amount` | `(line_subtotal - discount_amount) * (ppn_rate / 100)` |
| hitung | `pph_rate` | dari header PO (`pph`) |
| hitung | `pph_amount` | `(line_subtotal - discount_amount) * (pph_rate / 100)` |
| hitung | `total` | `line_subtotal - discount_amount + ppn_amount - pph_amount` |
| `created_date` | `created_at` | parse tanggal |
| `updated_date` | `updated_at` | parse tanggal |
| `prsnumber`, `department_code`, `created_by` | `meta` | disimpan sebagai metadata legacy |

Catatan:
- `po_detail.is_active = N` tidak diimport sebagai line aktif.
- Jika `prs_item_id` tidak bisa dipetakan, line tetap diimport dengan `prs_item_id = NULL` agar data historis tidak hilang.
- Untuk DB engine SQL Server, `purchase_order_items.id` akan auto-increment (legacy id tetap disimpan di `meta.legacy_detail_id`) karena constraint `IDENTITY`.

## Seeder Source

Seeder `PurchaseOrderSeeder` mendukung dua sumber:

1. `legacy` database:
   - set `SEED_SOURCE=legacy`
   - konfigurasi tabel/koneksi pada `config/legacy_import.php` untuk dataset `po` dan `po_detail`
2. `local` CSV:
   - set `SEED_SOURCE=local`
   - file default:
     - `public/document/csv/po.csv`
     - `public/document/csv/po_detail.csv`

Jika mode `legacy` gagal dan fallback aktif, seeder otomatis fallback ke CSV.

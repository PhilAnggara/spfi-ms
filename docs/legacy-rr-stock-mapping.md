# Legacy Mapping: RR and Stock

Dokumen ini merangkum mapping dari sistem lama ke sistem baru untuk alur:
`PRS -> PO -> Approval -> Receiving Report -> Stock`.

## Ringkasan Alur Bisnis Baru

1. PRS dibuat dan item PRS menjadi dasar pembuatan PO item.
2. PO disubmit lalu di-approve (`PENDING_APPROVAL` -> `APPROVED`).
3. RR dibuat berdasarkan PO, item RR harus tertaut ke `purchase_order_items`.
4. Progress delivery PRS dihitung dari akumulasi `receiving_report_items.qty_good`.
5. Pergerakan stok operasional berjalan dari proses RR pada sistem baru (`StockService`).

## Catatan Penyesuaian Mapping

- Karena flow baru mensyaratkan RR item terhubung ke PO item, mapping `rr_detail` diprioritaskan ke `purchase_order_item_id` (berdasarkan `po_code` dari header RR, `product_code`, dan `prs_code` bila tersedia).
- Field legacy yang tidak menjadi kolom inti flow baru tetap disimpan pada `meta` untuk audit:
  - `receiving_reports.meta`
  - `receiving_report_items.meta`
- Untuk data legacy `stock_inventory` dan `stock_balance`, `item_id` dipetakan ulang berdasarkan `product_code` ke tabel `items` sistem baru.

## Mapping Tabel RR Header

Legacy `rr` -> New `receiving_reports`

- `rr_code` -> `rr_number`
- `po_code` -> `purchase_order_id` (lookup ke `purchase_orders.po_number`)
- `rr_date` -> `received_date`
- `Is_BC` (`Y/N`) -> `requires_customs_document` (`true/false`)
- `rr_remarks` -> `notes`
- `created_by` -> `created_by` (lookup user)
- `created_date` -> `created_at`
- `updated_date` -> `updated_at`
- `is_active` -> `deleted_at` (`N` -> `updated_date`, `Y` -> `null`)
- `evaluated_by`, `evaluated_date`, `approved_by`, `approved_date`, `rr_from`, `updated_by` -> `meta`
- `customs_document_type_id` diisi default tipe BC pemasukan (`BC 2.7`/`Pemasukan`) jika `requires_customs_document = true`.

## Mapping Tabel RR Detail

Legacy `rr_detail` -> New `receiving_report_items`

- `rr_code` -> `receiving_report_id` (lookup ke `receiving_reports.rr_number`)
- `product_code` + `prs_code` + PO context -> `purchase_order_item_id`
- `qty_g` -> `qty_good`
- `qty_b` -> `qty_bad`
- `created_date` -> `created_at`
- `updated_date` -> `updated_at`
- `id`, `department_code`, `uom`, `unit_cost`, `amount`, `prs_code` -> `meta`
- `is_active = N` -> detail di-skip

## Mapping Tabel Stock Inventory

Legacy `stock_inventory` -> New `stock_inventories`

- `product_code` -> `product_code`
- `product_code` -> `item_id` (lookup ke `items.code`)
- `wh_code` -> `wh_code` (default `MAIN`)
- `balance` -> `balance`
- `start_balance` -> `start_balance`
- `average_price` -> `average_price`
- `is_active` -> `is_active`
- `is_delete` -> `is_delete`
- `created_by` -> `created_by` (lookup user)
- `updated_by` -> `updated_by` (lookup user)
- `created_date` -> `created_at`
- `updated_date` -> `updated_at`

Setelah import `stock_inventories`, `items.stock_on_hand` disinkronkan dari total balance aktif per item.

## Mapping Tabel Stock Balance

Legacy `stock_balance` -> New `stock_balances`

- `date` -> `date`
- `product_code` -> `product_code`
- `product_code` -> `item_id` (lookup ke `items.code`)
- `wh_code` -> `wh_code` (default `MAIN`)
- `begin` -> `begin`
- `qty_in1/2/3` -> `qty_in1/2/3`
- `qty_out1/2/3` -> `qty_out1/2/3`
- `end` -> `end`
- `acc_qty_in1` -> `acc_qty_in1`
- `acc_average_price_in1` -> `acc_average_price_in1`
- `acc_qty_total` -> `acc_qty_total`
- `acc_average_price_total` -> `acc_average_price_total`
- `reference_type` -> `reference_type`
- `reference_id` -> `reference_id`
- `reference_line_id` -> `reference_line_id`
- `created_by` -> `created_by` (lookup user)
- `created_date` -> `created_at`
- `updated_date` -> `updated_at`

## Sumber Data Seeder

Semua seeder mendukung dual-source:

- `SEED_SOURCE=legacy`: baca dari database lama
- `SEED_SOURCE=local`: baca dari CSV di `public/document/csv`

Dataset yang dipakai:

- `rr` -> `rr.csv`
- `rr_detail` -> `rr_detail.csv`
- `stock_inventory` -> `stock_inventory.csv`
- `stock_balance` -> `stock_balance.csv`

Jika `SEED_SOURCE=legacy` gagal dan fallback aktif, source otomatis pindah ke CSV.

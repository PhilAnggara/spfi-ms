# Accounting Inventory — Legacy-shaped tables

Posted inventory data mirrors AISystem. Only two tables are used — no draft/ledger stack.

| New table | Legacy table | Role |
|-----------|--------------|------|
| `accounting_inventory_doc_tran` | `AISystem.DocTran` | Flat journal: 1 row = 1 item movement |
| `accounting_inventory_monthly` | `AISystem.tbl_InventoryMonthly` | Monthly stock-card lines (`Begining`/`Ending`) |

## Column map (`DocTran`)

| Legacy | New |
|--------|-----|
| `TranId` | `legacy_tran_id` (import) / `id` |
| `DocCode` | `doc_code` |
| `DocNo` | `doc_no` |
| `DocDate` | `doc_date` |
| `PoNo` | `po_no` |
| `ICode` | `item_code` |
| `Qty` | `qty` (**signed**: + in, − out) |
| `UCost` | `u_cost` |
| `Uom` | `uom` |
| `AveCost` | `ave_cost` |
| `TQty` | `t_qty` |
| `TranDate` | `tran_date` |
| `InputTime` | `input_time` |
| `ModifyDate` | `modify_date` |
| `Category` | `category` (string name, includes `MATERIAL IN TRANSIT`) |
| `Amount` | `amount` |

Additional local IDs (alongside codes): `item_id`, `category_id`, `source_type`/`source_id`, `supplier_id`, `purchase_order_id`, plus encode audit (`encoded_by`, `encoded_at`, `party_*`, `remarks`, `is_corrected`).

## Queue / encode

- Queue is built from source documents (RR/TS/DR). Status **Encoded** when matching rows exist in `doc_tran` (`doc_code` + `doc_no` + `category_id`).
- Encode posts directly to `doc_tran` + `monthly` (no draft tables).
- CV/JV create encodes immediately into the same two tables.
- Available qty / reports read from monthly / doc_tran.

## Import

```bash
php artisan accounting-inventory:import-legacy --truncate
php artisan accounting-inventory:import-legacy --from-year=2024
php artisan accounting-inventory:import-legacy --dry-run
php artisan accounting-inventory:validate-legacy-parity --year=2024 --month=1
```

Default import is **full history** (no year floor). Use `--from-year=YYYY` for a selective backfill. Idempotent via `legacy_tran_id` / `legacy_monthly_id`. Pass `--truncate` to clear both tables before a full reload.

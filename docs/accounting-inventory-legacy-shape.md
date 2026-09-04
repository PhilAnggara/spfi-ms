# Accounting Inventory — Legacy-shaped tables

Posted inventory data mirrors AISystem so legacy programmers can read it without learning a new model.

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

Optional FKs: `item_id`, `category_id` (resolved from master when possible).

## Encode

Draft UI may still use `accounting_inventory_transactions` / lines. On encode, rows are also written to `accounting_inventory_doc_tran` + `accounting_inventory_monthly`. GL / Doc Entry is not used in this phase.

## Import

```bash
php artisan accounting-inventory:import-legacy --from-year=2024
php artisan accounting-inventory:import-legacy --from-year=2016 --dry-run
php artisan accounting-inventory:validate-legacy-parity --year=2024 --month=1
```

Default import starts at 2024-01-01; pass an earlier `--from-year` to backfill. Idempotent via `legacy_tran_id` / `legacy_monthly_id`.

# TODO LIST

## 🔧 Bug
- Pagination on master product

## 🚀 Improvement
- Add filter on master product

## 🧹 Cleanup
- Remove unused code in master product


* [rr]
- rr_code
- rr_date
- po_code
- rr_from
- rr_remarks
- evaluated_by
- evaluated_date
- approved_by
- approved_date
- is_active
- created_by
- created_date
- updated_by
- updated_date
- Is_BC

[receiving_reports]
- id
- rr_number
- purchase_order_id
- received_date
- notes
- created_by
- created_at
- updated_at
- deleted_at

* [rr_detail]
- id
- rr_code
- prs_code
- product_code
- department_code
- qty_g
- qty_b
- uom
- unit_cost
- amount
- created_by
- created_date
- updated_by
- updated_date
- is_active

[receiving_report_items]
- id
- receiving_report_id
- purchase_order_item_id
- qty_good
- qty_bad
- created_at
- updated_at

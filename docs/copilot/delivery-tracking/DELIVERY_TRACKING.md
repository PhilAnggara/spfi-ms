# PRS Delivery Tracking Feature

## Overview
Sistem tracking otomatis untuk memantau status barang yang dipesan melalui Purchase Requisition Slip (PRS). Feature ini melacak berapa banyak item yang sudah diterima (via Receiving Report) dan otomatis mengubah status PRS ketika semua item telah diterima penuh.

## Features

### 1. Item-Level Delivery Tracking
Setiap PRS item menampilkan:
- **Qty Ordered**: Jumlah barang yang dipesan
- **Qty Delivered**: Jumlah barang yang sudah diterima (dari RR)
- **Delivery Status**: 
  - ✅ **RECEIVED** - Semua barang sudah diterima (qty delivered = qty ordered)
  - ⏳ **PARTIAL** - Sebagian barang diterima (0 < qty delivered < qty ordered)
  - ❌ **PENDING** - Belum ada barang yang diterima (qty delivered = 0)
- **Progress Bar**: Visual indicator showing delivery progress percentage

### 2. PRS-Level Delivery Status
Setiap PRS record menampilkan overall delivery status berdasarkan status items:
- **RECEIVED** - Semua items fully received
- **PARTIAL** - Ada items yang partially atau fully received
- **PENDING** - Semua items belum ada yang diterima

### 3. Automatic Status Update
Ketika semua PRS items sudah RECEIVED (full delivery):
- PRS `status` akan otomatis berubah dari `APPROVED` menjadi `DELIVERY_COMPLETE`
- Trigger terjadi saat Receiving Report (RR) dibuat atau diupdate

## Data Model

### PrsItem Methods
```php
$item->getDeliveredQuantityAttribute()  // int: Total qty received
$item->getDeliveryStatusAttribute()     // string: PENDING|PARTIAL|RECEIVED
$item->getDeliveryProgressAttribute()   // int: 0-100 percentage
```

### Prs Methods
```php
$prs->getOverallDeliveryStatusAttribute()   // string: PENDING|PARTIAL|RECEIVED
$prs->getOverallDeliveryProgressAttribute() // int: 0-100 percentage
$prs->isDeliveryComplete()                  // bool: Whether all items received
$prs->checkAndUpdateDeliveryStatus()        // void: Auto-update status if complete
```

## Database Relations
```
PRS (1) ──→ (M) PrsItem
PrsItem (1) ──→ (1) PurchaseOrderItem
PurchaseOrderItem (1) ──→ (M) ReceivingReportItem
ReceivingReportItem {qty_good, qty_bad}
```

**Delivered Qty Calculation:**
```
Delivered Qty = SUM(ReceivingReportItem.qty_good) 
                where PurchaseOrderItem linked to PrsItem
```

## User Interface

### PRS List View (pages/prs.blade.php)
- Displays two badges when PRS status is APPROVED:
  - **Approval Status** (APPROVED, DRAFT, etc.)
  - **Delivery Status** (PENDING, PARTIAL, RECEIVED) with icon and progress color:
    - 🟢 Green: RECEIVED
    - 🟡 Yellow: PARTIAL
    - 🔴 Red: PENDING

### PRS Detail Modal (includes/modals/prs-modal.blade.php)
- Items table with columns:
  - Item Code
  - Item Name
  - Stock on Hand
  - Qty Ordered
  - Qty Delivered
  - Delivery Status (badge)
  - Progress Bar (0-100%)
  - Canvasser

## Implementation Details

### PrsItem.php
Added three accessor methods to calculate delivery status in real-time:
- Uses eager-loaded `purchaseOrderItem.receivingReportItems` relationship
- Sums `qty_good` from all RR items for that PO item

### Prs.php
Added four methods for overall delivery monitoring:
- Aggregate status from all items
- Calculate overall progress percentage
- Check if all items delivered
- Auto-update PRS status to DELIVERY_COMPLETE

### ReceivingReportController.php
Updated `store()` and `update()` methods:
- After creating/updating RR, calls `checkPrsDeliveryStatus()`
- Finds all affected PRS records
- Calls `checkAndUpdateDeliveryStatus()` on each

### PrsController.php
Updated `index()` method:
- Added eager load: `items.purchaseOrderItem.receivingReportItems`
- Ensures PrsItem can calculate delivered_quantity without N+1 queries

## Usage Examples

### Check if PRS is fully delivered
```php
$prs = Prs::find(1);
if ($prs->isDeliveryComplete()) {
    // All items fully received
}
```

### Get delivery status for single item
```php
$item = PrsItem::find(1);
echo $item->delivery_status;        // "PARTIAL"
echo $item->delivered_quantity;     // 50
echo $item->delivery_progress;      // 50
```

### Manually trigger status check
```php
$prs = Prs::find(1);
if ($prs->checkAndUpdateDeliveryStatus()) {
    // Status was updated to DELIVERY_COMPLETE
}
```

## Status Transitions

```
DRAFT
  └─→ SUBMITTED
      └─→ ON_HOLD (optional)
          └─→ RESUBMITTED
              └─→ APPROVED
                  └─→ DELIVERY_COMPLETE (automatic when all items received)
```

**Note:** DELIVERY_COMPLETE is set automatically. Only APPROVED PRS records can transition.

## Testing Scenario

1. Create PRS with multiple items
2. Approve PRS (APPROVED status)
3. Create Receiving Report for PO with partial qty
   - PrsItem status shows PARTIAL
   - PRS overall status shows PARTIAL
   - PRS status remains APPROVED
4. Create another RR with remaining qty
   - PrsItem status shows RECEIVED
   - PRS overall status shows RECEIVED
   - **PRS status auto-updates to DELIVERY_COMPLETE**

## Performance Considerations

- Using accessor methods (dynamic attributes) for real-time calculation
- Requires eager-loading `purchaseOrderItem.receivingReportItems` to avoid N+1
- Already handled in PrsController.index() with proper `with()` clause
- Progress calculation uses collection methods (optimized for small sets)

## Future Enhancements

- [ ] Add delivery timeline chart (ordered → partial → complete dates)
- [ ] Send notification when PRS reaches DELIVERY_COMPLETE
- [ ] Add API endpoint for delivery status queries
- [ ] Implement delivery date tracking per item
- [ ] Add dashboard widget showing delivery pending items

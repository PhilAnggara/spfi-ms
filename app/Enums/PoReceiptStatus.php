<?php

namespace App\Enums;

enum PoReceiptStatus: string
{
    case NotReceived = 'not_received';
    case Partial = 'partial';
    case FullyReceived = 'fully_received';

    public function label(): string
    {
        return match ($this) {
            self::NotReceived => 'Not Received',
            self::Partial => 'Partial',
            self::FullyReceived => 'Fully Received',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotReceived => 'badge bg-light-secondary text-secondary',
            self::Partial => 'badge bg-light-warning text-warning',
            self::FullyReceived => 'badge bg-light-success text-success',
        };
    }

    public static function fromQuantities(float $ordered, float $received): self
    {
        if ($received <= 0.0) {
            return self::NotReceived;
        }

        if ($ordered > 0.0 && $received < $ordered) {
            return self::Partial;
        }

        return self::FullyReceived;
    }

    /**
     * Correlated subquery: total ordered qty for the outer purchase_orders row.
     */
    public static function qtyOrderedSubquerySql(string $purchaseOrdersAlias = 'purchase_orders'): string
    {
        return "(SELECT COALESCE(SUM(poi.quantity), 0)
            FROM purchase_order_items AS poi
            WHERE poi.purchase_order_id = {$purchaseOrdersAlias}.id)";
    }

    /**
     * Correlated subquery: total received qty (good + bad) for active RR lines.
     */
    public static function qtyReceivedSubquerySql(string $purchaseOrdersAlias = 'purchase_orders'): string
    {
        return "(SELECT COALESCE(SUM(rri.qty_good + rri.qty_bad), 0)
            FROM receiving_report_items AS rri
            INNER JOIN receiving_reports AS rr ON rr.id = rri.receiving_report_id
            WHERE rr.purchase_order_id = {$purchaseOrdersAlias}.id
              AND rr.deleted_at IS NULL
              AND rri.deleted_at IS NULL)";
    }
}

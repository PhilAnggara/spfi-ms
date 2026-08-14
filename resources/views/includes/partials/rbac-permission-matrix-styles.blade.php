@once
<style>
    .rbac-perm-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: .75rem 1rem;
        margin-bottom: 1rem;
    }
    .rbac-perm-search {
        position: relative;
        flex: 1 1 16rem;
        max-width: 28rem;
    }
    .rbac-perm-search > i {
        position: absolute;
        left: .85rem;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: .85rem;
        pointer-events: none;
    }
    .rbac-perm-search .form-control {
        padding-left: 2.35rem;
        border-color: rgba(67, 94, 190, .16);
        border-radius: .65rem;
    }
    .rbac-perm-search .form-control:focus {
        border-color: rgba(67, 94, 190, .45);
        box-shadow: 0 0 0 .2rem rgba(67, 94, 190, .12);
    }
    .rbac-perm-legend {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .35rem;
        font-size: .78rem;
    }
    .rbac-perm-legend-sep {
        opacity: .45;
    }
    .rbac-perm-empty {
        text-align: center;
        color: #94a3b8;
        padding: 1.75rem 1rem;
        border: 1px dashed rgba(15, 23, 42, .12);
        border-radius: .65rem;
        margin-bottom: 1rem;
    }
    .rbac-perm-empty i {
        display: block;
        margin-bottom: .4rem;
        opacity: .55;
        font-size: 1.25rem;
    }

    .rbac-perm-group {
        border: 1px solid rgba(67, 94, 190, .12);
        border-radius: .65rem;
        overflow: hidden;
        background: #fff;
        margin-bottom: .85rem;
    }
    .rbac-perm-group:last-child {
        margin-bottom: 0;
    }
    .rbac-perm-group-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .65rem .9rem;
        background: rgba(67, 94, 190, .04);
        border-bottom: 1px solid rgba(67, 94, 190, .1);
    }
    .rbac-perm-group-title {
        margin: 0;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #435ebe;
    }
    .rbac-perm-group-count {
        min-width: 1.5rem;
        height: 1.5rem;
        padding: 0 .45rem;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .72rem;
        font-weight: 700;
        color: #435ebe;
        background: rgba(67, 94, 190, .12);
    }

    .rbac-perm-table-wrap {
        overflow-x: auto;
    }
    .rbac-perm-matrix {
        width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        font-size: .875rem;
    }
    .rbac-perm-matrix thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        padding: .55rem .7rem;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: #64748b;
        background: #f8fafc;
        border-bottom: 1px solid rgba(15, 23, 42, .08);
        white-space: nowrap;
    }
    .rbac-perm-matrix tbody td {
        padding: .55rem .7rem;
        border-bottom: 1px solid rgba(15, 23, 42, .06);
        vertical-align: middle;
    }
    .rbac-perm-matrix tbody tr:last-child td {
        border-bottom: 0;
    }
    .rbac-perm-matrix tbody tr:hover td {
        background: rgba(67, 94, 190, .03);
    }

    .rbac-perm-col-resource {
        min-width: 10rem;
        width: 12rem;
    }
    .rbac-perm-col-crud {
        width: 4.25rem;
        min-width: 4.25rem;
        text-align: center;
    }
    .rbac-perm-col-other {
        min-width: 12rem;
    }
    .rbac-perm-resource-name {
        display: block;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.3;
    }
    .rbac-perm-na {
        display: inline-flex;
        width: 1.25rem;
        justify-content: center;
        color: #cbd5e1;
        font-weight: 700;
        line-height: 1;
    }

    .rbac-perm-cell {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.85rem;
        height: 1.85rem;
        margin: 0 auto;
        border-radius: .45rem;
        cursor: pointer;
    }
    .rbac-perm-cell.is-via-role {
        background: rgba(22, 163, 74, .08);
    }
    .rbac-perm-cell.is-disabled {
        cursor: not-allowed;
        opacity: .65;
    }
    .rbac-perm-cell-input {
        margin: 0;
        float: none;
        cursor: inherit;
    }

    .rbac-perm-other-list {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
    }
    .rbac-perm-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        max-width: 100%;
        margin: 0;
        padding: .28rem .55rem .28rem .4rem;
        border: 1px solid rgba(15, 23, 42, .1);
        border-radius: .5rem;
        background: #f8fafc;
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease;
    }
    .rbac-perm-chip:hover {
        background: #fff;
        border-color: rgba(67, 94, 190, .28);
    }
    .rbac-perm-chip.is-checked {
        background: rgba(67, 94, 190, .06);
        border-color: rgba(67, 94, 190, .28);
    }
    .rbac-perm-chip.is-via-role {
        background: rgba(22, 163, 74, .07);
        border-color: rgba(22, 163, 74, .22);
    }
    .rbac-perm-chip.is-disabled {
        cursor: not-allowed;
        opacity: .65;
    }
    .rbac-perm-chip-input {
        margin: 0;
        float: none;
        flex-shrink: 0;
        cursor: inherit;
    }
    .rbac-perm-chip-label {
        font-size: .78rem;
        font-weight: 600;
        color: #334155;
        line-height: 1.2;
        text-transform: capitalize;
        white-space: nowrap;
    }
    .rbac-perm-via-role-text {
        color: #15803d !important;
    }

    .rbac-perm-via-dot {
        width: .42rem;
        height: .42rem;
        border-radius: 50%;
        background: #16a34a;
        flex-shrink: 0;
        box-shadow: 0 0 0 2px rgba(22, 163, 74, .15);
    }
    .rbac-perm-cell .rbac-perm-via-dot {
        position: absolute;
        top: .12rem;
        right: .12rem;
    }
    .rbac-perm-via-dot.is-legend {
        display: inline-block;
        vertical-align: middle;
        margin-right: .15rem;
    }

    .rbac-role-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr));
        gap: .55rem;
    }
    .rbac-role-chip {
        display: flex;
        align-items: center;
        gap: .55rem;
        margin: 0;
        padding: .55rem .7rem;
        border: 1px solid rgba(67, 94, 190, .12);
        border-radius: .55rem;
        background: #fff;
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease;
    }
    .rbac-role-chip:hover {
        background: rgba(67, 94, 190, .03);
        border-color: rgba(67, 94, 190, .28);
    }
    .rbac-role-chip .form-check-input {
        margin: 0;
        float: none;
        flex-shrink: 0;
    }
    .rbac-role-chip-name {
        font-size: .82rem;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.25;
        word-break: break-word;
    }
</style>
@endonce

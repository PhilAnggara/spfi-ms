@once
    <style>
        .rbac-user-list {
            border: 1px solid rgba(67, 94, 190, .12);
            border-radius: .65rem;
            overflow: hidden;
            background: #fff;
        }
        .rbac-user-row {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: .7rem .9rem;
            border-bottom: 1px solid rgba(15, 23, 42, .06);
        }
        .rbac-user-row:last-child { border-bottom: 0; }
        .rbac-user-row:hover { background: rgba(67, 94, 190, .03); }
        .rbac-user-initials {
            width: 2.15rem;
            height: 2.15rem;
            border-radius: .55rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .02em;
            color: #435ebe;
            background: rgba(67, 94, 190, .1);
        }
        .rbac-user-name {
            font-weight: 600;
            line-height: 1.25;
            color: #1e293b;
        }
        .rbac-user-meta {
            font-size: .8rem;
            color: #64748b;
            line-height: 1.3;
        }
        .rbac-user-empty {
            padding: 1.75rem 1rem;
            text-align: center;
            color: #94a3b8;
        }
        .rbac-user-empty i {
            display: block;
            margin-bottom: .4rem;
            opacity: .55;
        }
        .rbac-source-badges {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem;
            margin-top: .3rem;
        }
    </style>
@endonce

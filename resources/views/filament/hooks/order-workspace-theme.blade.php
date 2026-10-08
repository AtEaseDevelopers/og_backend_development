{{-- Order workspace (Orders list, order detail, create order) — layout per "O&G Original UI Screens" pages 1-3. --}}
<style>
    .ow-page {
        --ow-bg: #ffffff;
        --ow-soft: #f8fafc;
        --ow-soft-2: #f1f5f9;
        --ow-line: #e5e7eb;
        --ow-line-2: #eef0f3;
        --ow-text: #0f172a;
        --ow-muted: #64748b;
        --ow-faint: #94a3b8;
        --ow-link: #1d4ed8;
        --ow-primary: #0f172a;
        --ow-primary-text: #ffffff;
        --ow-shadow: 0 1px 2px rgb(15 23 42 / .04);

        --ow-progress-bg: #eff6ff; --ow-progress-fg: #1d4ed8; --ow-progress-bd: #bfdbfe;
        --ow-customer-bg: #f5f3ff; --ow-customer-fg: #6d28d9; --ow-customer-bd: #ddd6fe;
        --ow-action-bg: #fffbeb; --ow-action-fg: #b45309; --ow-action-bd: #fde68a;
        --ow-issue-bg: #fef2f2; --ow-issue-fg: #b91c1c; --ow-issue-bd: #fecaca;
        --ow-released-bg: #ecfdf5; --ow-released-fg: #047857; --ow-released-bd: #a7f3d0;
        --ow-done-bg: #f0fdf4; --ow-done-fg: #15803d; --ow-done-bd: #bbf7d0;
        --ow-gray-bg: #f3f4f6; --ow-gray-fg: #4b5563; --ow-gray-bd: #e5e7eb;

        color: var(--ow-text);
        font-size: .875rem;
    }

    .dark .ow-page {
        --ow-bg: #111827;
        --ow-soft: #0f172a;
        --ow-soft-2: #1f2937;
        --ow-line: #374151;
        --ow-line-2: #1f2937;
        --ow-text: #f1f5f9;
        --ow-muted: #94a3b8;
        --ow-faint: #64748b;
        --ow-link: #93c5fd;
        --ow-primary: #f1f5f9;
        --ow-primary-text: #0f172a;
        --ow-shadow: none;

        --ow-progress-bg: rgb(30 64 175 / .25); --ow-progress-fg: #93c5fd; --ow-progress-bd: rgb(59 130 246 / .35);
        --ow-customer-bg: rgb(91 33 182 / .25); --ow-customer-fg: #c4b5fd; --ow-customer-bd: rgb(139 92 246 / .35);
        --ow-action-bg: rgb(146 64 14 / .25); --ow-action-fg: #fcd34d; --ow-action-bd: rgb(245 158 11 / .35);
        --ow-issue-bg: rgb(153 27 27 / .25); --ow-issue-fg: #fca5a5; --ow-issue-bd: rgb(239 68 68 / .35);
        --ow-released-bg: rgb(6 95 70 / .3); --ow-released-fg: #6ee7b7; --ow-released-bd: rgb(16 185 129 / .35);
        --ow-done-bg: rgb(22 101 52 / .3); --ow-done-fg: #86efac; --ow-done-bd: rgb(34 197 94 / .35);
        --ow-gray-bg: rgb(55 65 81 / .45); --ow-gray-fg: #cbd5e1; --ow-gray-bd: #374151;
    }

    .ow-page > section { padding-top: .5rem !important; }

    /* --- header ------------------------------------------------------- */
    .ow-crumb { font-size: .75rem; color: var(--ow-muted); margin-bottom: .25rem; }
    .ow-page a.ow-back { justify-self: start; width: fit-content; display: inline-flex; gap: .35rem; align-items: center; font-size: .8125rem; font-weight: 600; color: var(--ow-text); margin-bottom: .9rem; padding: .4rem .8rem; border: 1px solid var(--ow-line); border-radius: .5rem; background-color: var(--ow-bg); text-decoration: none; box-shadow: var(--ow-shadow); }
    .ow-page a.ow-back:hover { background-color: var(--ow-soft-2); text-decoration: none; }
    .ow-head { display: flex; gap: 1rem; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; margin-bottom: 1rem; }
    .ow-title { font-size: 1.5rem; line-height: 1.2; font-weight: 700; letter-spacing: -.01em; }
    .ow-title-mono { font-size: 1.6rem; font-weight: 700; letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
    .ow-sub { color: var(--ow-muted); margin-top: .25rem; }
    .ow-chips { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .5rem; align-items: center; }

    /* --- buttons ------------------------------------------------------ */
    .ow-btn { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; border-radius: .5rem; padding: .5rem .9rem; font-size: .8125rem; font-weight: 600; border: 1px solid var(--ow-line); background: var(--ow-bg); color: var(--ow-text); cursor: pointer; white-space: nowrap; transition: background-color .12s, border-color .12s, opacity .12s; }
    .ow-btn:hover { background: var(--ow-soft-2); }
    .ow-btn[disabled] { opacity: .5; cursor: not-allowed; }
    .ow-btn-primary { background: var(--ow-primary); border-color: var(--ow-primary); color: var(--ow-primary-text); }
    .ow-btn-primary:hover { background: var(--ow-primary); opacity: .9; }
    .ow-btn-success { background: #15803d; border-color: #15803d; color: #fff; }
    .ow-btn-success:hover { background: #166534; }
    .ow-btn-danger { color: #b91c1c; border-color: #fecaca; }
    .dark .ow-page .ow-btn-danger { color: #fca5a5; border-color: rgb(239 68 68 / .4); }
    .ow-btn-danger:hover { background: var(--ow-issue-bg); }
    .ow-btn-sm { padding: .3rem .6rem; font-size: .75rem; }
    .ow-btn-link { border: 0; background: none; padding: 0; color: var(--ow-link); font-weight: 600; font-size: .8125rem; cursor: pointer; }
    .ow-btn-link:hover { text-decoration: underline; background: none; }
    .ow-actions { display: flex; gap: .5rem; align-items: center; justify-content: flex-end; flex-wrap: wrap; }
    .ow-actions-split { display: flex; gap: .5rem; align-items: center; justify-content: space-between; flex-wrap: wrap; }

    /* --- cards -------------------------------------------------------- */
    .ow-card { background: var(--ow-bg); border: 1px solid var(--ow-line); border-radius: .75rem; box-shadow: var(--ow-shadow); }
    .ow-card-pad { padding: 1rem 1.1rem; }
    .ow-card-title { font-size: .95rem; font-weight: 600; margin-bottom: .75rem; display: flex; justify-content: space-between; align-items: center; gap: .5rem; }
    .ow-card-sub { color: var(--ow-muted); font-size: .75rem; margin-top: -.5rem; margin-bottom: .75rem; }
    .ow-stack { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
    .ow-grid-main { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr); align-items: start; }
    .ow-grid-2 { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 1023px) { .ow-grid-main, .ow-grid-2 { grid-template-columns: minmax(0, 1fr); } }
    /* order detail overview: wide left (customer & order), narrow right (record ownership → payment summary → linked records) */
    .ow-grid-overview { display: grid; gap: 1rem; grid-template-columns: minmax(0, 70fr) minmax(0, 30fr); align-items: start; }
    @media (max-width: 1099px) { .ow-grid-overview { grid-template-columns: minmax(0, 1fr); } }
    .ow-anchor { scroll-margin-top: 6rem; }
    .ow-section-title { font-size: 1rem; font-weight: 700; margin: .5rem 0 .25rem; scroll-margin-top: 5rem; }

    .ow-dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem 1.25rem; }
    .ow-dl-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    @media (max-width: 640px) { .ow-dl, .ow-dl-3 { grid-template-columns: minmax(0, 1fr); } }
    .ow-dt { font-size: .7rem; color: var(--ow-muted); margin-bottom: .1rem; }
    .ow-dd { font-size: .85rem; overflow-wrap: anywhere; }
    .ow-kv { display: flex; justify-content: space-between; gap: 1rem; padding: .55rem 0; border-bottom: 1px solid var(--ow-line-2); }
    .ow-kv:last-child { border-bottom: 0; }
    .ow-kv strong { font-variant-numeric: tabular-nums; }
    .ow-note { font-size: .75rem; color: var(--ow-muted); }
    .ow-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8rem; }
    .ow-link { color: var(--ow-link); font-weight: 600; }
    .ow-link:hover { text-decoration: underline; }

    .ow-stop { display: flex; gap: .65rem; align-items: flex-start; margin-top: .65rem; }
    .ow-stop-badge { flex: none; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: var(--ow-soft-2); color: var(--ow-muted); font-size: .7rem; font-weight: 700; display: grid; place-items: center; }
    .ow-stop-title { font-weight: 600; }
    .ow-stop-sub { font-size: .75rem; color: var(--ow-muted); }

    /* --- pills -------------------------------------------------------- */
    .ow-pill { display: inline-flex; align-items: center; gap: .3rem; border-radius: .375rem; padding: .15rem .5rem; font-size: .7rem; font-weight: 600; line-height: 1.3; border: 1px solid transparent; white-space: nowrap; }
    .ow-pill::before { content: ''; width: .4rem; height: .4rem; border-radius: 9999px; background: currentColor; }
    .ow-pill-plain::before { display: none; }
    .ow-pill-progress { background: var(--ow-progress-bg); color: var(--ow-progress-fg); border-color: var(--ow-progress-bd); }
    .ow-pill-customer { background: var(--ow-customer-bg); color: var(--ow-customer-fg); border-color: var(--ow-customer-bd); }
    .ow-pill-action { background: var(--ow-action-bg); color: var(--ow-action-fg); border-color: var(--ow-action-bd); }
    .ow-pill-issue { background: var(--ow-issue-bg); color: var(--ow-issue-fg); border-color: var(--ow-issue-bd); }
    .ow-pill-released { background: var(--ow-released-bg); color: var(--ow-released-fg); border-color: var(--ow-released-bd); }
    .ow-pill-done { background: var(--ow-done-bg); color: var(--ow-done-fg); border-color: var(--ow-done-bd); }
    .ow-pill-gray { background: var(--ow-gray-bg); color: var(--ow-gray-fg); border-color: var(--ow-gray-bd); }
    .ow-pill-dark { background: var(--ow-soft-2); color: var(--ow-text); border-color: var(--ow-line); }

    /* --- list page ---------------------------------------------------- */
    .ow-cards { display: grid; gap: .75rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    @media (max-width: 900px) { .ow-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ow-stat { text-align: left; border: 1px solid var(--ow-line); background: var(--ow-bg); border-radius: .75rem; padding: .85rem 1rem; cursor: pointer; transition: border-color .12s, transform .12s; box-shadow: var(--ow-shadow); }
    .ow-stat:hover { border-color: var(--ow-faint); }
    .ow-stat-label { font-weight: 600; font-size: .85rem; }
    .ow-stat-value { font-size: 1.5rem; font-weight: 700; margin: .15rem 0; font-variant-numeric: tabular-nums; }
    .ow-stat-hint { font-size: .72rem; color: var(--ow-muted); }
    .ow-stat-attention .ow-stat-value { color: #b45309; }
    .ow-stat-customer .ow-stat-value { color: #6d28d9; }
    .ow-stat-bill .ow-stat-value { color: #15803d; }
    .dark .ow-page .ow-stat-attention .ow-stat-value { color: #fcd34d; }
    .dark .ow-page .ow-stat-customer .ow-stat-value { color: #c4b5fd; }
    .dark .ow-page .ow-stat-bill .ow-stat-value { color: #86efac; }
    .ow-stat-active { background: var(--ow-primary); border-color: var(--ow-primary); color: var(--ow-primary-text); }
    .ow-stat-active .ow-stat-hint, .ow-stat-active .ow-stat-value { color: var(--ow-primary-text) !important; opacity: .95; }

    /* order stage tags (filter the list) */
    .ow-tags { display: flex; flex-wrap: wrap; gap: .45rem; }
    .ow-tag { --tag-bg: var(--ow-bg); --tag-fg: var(--ow-text); --tag-bd: var(--ow-line);
        display: inline-flex; align-items: center; gap: .4rem; border-radius: .5rem; padding: .3rem .4rem .3rem .6rem;
        font-size: .75rem; font-weight: 600; line-height: 1.3; white-space: nowrap; cursor: pointer;
        background: var(--tag-bg); color: var(--tag-fg); border: 1px solid var(--tag-bd);
        transition: background-color .12s, border-color .12s, color .12s, opacity .12s; }
    .ow-tag::before { content: ''; width: .45rem; height: .45rem; border-radius: 9999px; background: currentColor; }
    .ow-tag:hover { border-color: var(--tag-fg); }
    .ow-tag:focus-visible { outline: 2px solid #3b82f6; outline-offset: 2px; }
    .dark .ow-page .ow-tag:focus-visible { outline-color: #93c5fd; }
    .ow-tag-count { min-width: 1.4rem; padding: .05rem .4rem; border-radius: 9999px; text-align: center; font-size: .7rem; font-variant-numeric: tabular-nums;
        background: color-mix(in srgb, currentColor 14%, transparent); }
    .ow-tag-all::before { display: none; }
    .ow-tag-progress { --tag-bg: var(--ow-progress-bg); --tag-fg: var(--ow-progress-fg); --tag-bd: var(--ow-progress-bd); }
    .ow-tag-customer { --tag-bg: var(--ow-customer-bg); --tag-fg: var(--ow-customer-fg); --tag-bd: var(--ow-customer-bd); }
    .ow-tag-action { --tag-bg: var(--ow-action-bg); --tag-fg: var(--ow-action-fg); --tag-bd: var(--ow-action-bd); }
    .ow-tag-issue { --tag-bg: var(--ow-issue-bg); --tag-fg: var(--ow-issue-fg); --tag-bd: var(--ow-issue-bd); }
    .ow-tag-released { --tag-bg: var(--ow-released-bg); --tag-fg: var(--ow-released-fg); --tag-bd: var(--ow-released-bd); }
    .ow-tag-done { --tag-bg: var(--ow-done-bg); --tag-fg: var(--ow-done-fg); --tag-bd: var(--ow-done-bd); }
    /* no orders in this stage: neutral colours, label kept readable */
    .ow-tag-empty:not(.ow-tag-active) { --tag-bg: var(--ow-bg); --tag-fg: var(--ow-muted); --tag-bd: var(--ow-line); }
    .ow-tag-empty:not(.ow-tag-active)::before { opacity: .45; }
    .ow-tag-active { background: var(--tag-fg); border-color: var(--tag-fg); color: var(--ow-bg); }

    .ow-filters { padding: .85rem 1rem 1rem; }
    .ow-filter-top { display: flex; gap: .75rem 1rem; align-items: flex-end; justify-content: flex-start; flex-wrap: wrap; margin-bottom: .75rem; }
    .ow-input, .ow-select, .ow-textarea { width: 100%; border: 1px solid var(--ow-line); background-color: var(--ow-bg); color: var(--ow-text); border-radius: .5rem; padding: .45rem .65rem; font-size: .8125rem; line-height: 1.4; }
    .ow-page select.ow-select {
        -webkit-appearance: none; appearance: none;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right .5rem center;
        background-size: 1.1rem 1.1rem;
        padding-right: 2rem;
        text-overflow: ellipsis;
    }
    .ow-input:focus, .ow-select:focus, .ow-textarea:focus { outline: 2px solid rgb(59 130 246 / .35); outline-offset: 0; border-color: #3b82f6; }
    .ow-input[readonly] { background: var(--ow-soft); color: var(--ow-muted); }
    .ow-search { flex: 1 1 14rem; max-width: 22rem; }
    .ow-filter-top .ow-actions { min-height: 2.15rem; }
    .ow-filter-top .ow-actions { margin-left: auto; }

    /* customer type badge beside the customer name: Cash · COD · Credit */
    .ow-ctype { display: inline-block; margin-left: .3rem; padding: 0 .35rem; border-radius: .25rem; border: 1px solid; font-size: .62rem; font-weight: 700; line-height: 1.5; letter-spacing: .02em; vertical-align: 1px; }
    .ow-ctype-cash { background: var(--ow-done-bg); color: var(--ow-done-fg); border-color: var(--ow-done-bd); }
    .ow-ctype-cod { background: var(--ow-action-bg); color: var(--ow-action-fg); border-color: var(--ow-action-bd); }
    .ow-ctype-term { background: var(--ow-progress-bg); color: var(--ow-progress-fg); border-color: var(--ow-progress-bd); }
    .ow-field label, .ow-label { display: block; font-size: .72rem; color: var(--ow-muted); margin-bottom: .25rem; }
    .ow-label .ow-req, .ow-field label .ow-req { color: #dc2626; }
    .ow-fgrid { display: grid; gap: .75rem 1rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .ow-fgrid + .ow-fgrid { margin-top: .75rem; }
    .ow-fgrid-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
    /* CSN list: transfer code / Subsheet / Break bulk tags under the CSN number */
    .ow-csn-tags { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .25rem; }
    .ow-csn-tag { display: inline-flex; align-items: center; padding: .05rem .4rem; border-radius: .3rem; border: 1px solid #fcd34d; background: #fffbeb; color: #92400e; font-size: .68rem; font-weight: 600; white-space: nowrap; }
    .ow-csn-tag-code { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .ow-csn-tag-bb { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
    .dark .ow-csn-tag { border-color: rgb(217 119 6 / .45); background: rgb(217 119 6 / .15); color: #fcd34d; }
    .dark .ow-csn-tag-code { border-color: rgb(59 130 246 / .45); background: rgb(59 130 246 / .15); color: #93c5fd; }
    .dark .ow-csn-tag-bb { border-color: rgb(239 68 68 / .45); background: rgb(239 68 68 / .15); color: #fca5a5; }
    @media (max-width: 1200px) { .ow-fgrid-5 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (max-width: 900px) { .ow-fgrid, .ow-fgrid-5 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ow-field-error { color: #dc2626; font-size: .72rem; margin-top: .2rem; }

    .ow-table-wrap { overflow-x: auto; }
    .ow-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .ow-table th { text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ow-muted); font-weight: 600; background: var(--ow-soft-2); padding: .6rem .75rem; border-bottom: 1px solid var(--ow-line); white-space: nowrap; }
    .ow-table th.ow-num { text-align: right; }
    /* Orders list column widths: Order / Customer widest, Amount and Next step compact */
    .ow-orders-table { min-width: 60rem; }
    .ow-orders-table .ow-col-order { width: 27%; }
    .ow-orders-table .ow-col-route { width: 18%; }
    .ow-orders-table .ow-col-stage { width: 20%; }
    .ow-orders-table .ow-col-payment { width: 17%; }
    .ow-orders-table .ow-col-amount { width: 9%; }
    .ow-orders-table .ow-col-next { width: 9%; }
    .ow-table td { padding: .75rem; border-bottom: 1px solid var(--ow-line-2); vertical-align: top; }
    .ow-table tbody tr:last-child td { border-bottom: 0; }

    /* sortable headers (▲ / ▼ on the sorted column) */
    .ow-th-sort { display: inline-flex; align-items: center; gap: .3rem; margin: 0; padding: 0; border: 0; background: none; font: inherit; color: inherit; text-transform: inherit; letter-spacing: inherit; white-space: nowrap; cursor: pointer; }
    .ow-th-sort:hover { color: var(--ow-text); }
    .ow-th-sort:focus-visible { outline: 2px solid #3b82f6; outline-offset: 2px; border-radius: .2rem; }
    .dark .ow-page .ow-th-sort:focus-visible { outline-color: #93c5fd; }
    .ow-sort-ind { font-size: .62rem; line-height: 1; opacity: .35; transition: opacity .12s; }
    .ow-th-sort:hover .ow-sort-ind { opacity: .7; }
    .ow-table th.ow-th-sorted { color: var(--ow-text); }
    .ow-th-sorted .ow-sort-ind { opacity: 1; }
    .ow-row { cursor: pointer; transition: background-color .1s; }
    .ow-row:hover td { background: var(--ow-soft); }
    .ow-row td:first-child { border-left: 3px solid var(--ow-gray-bd); }
    .ow-row-progress td:first-child { border-left-color: #2563eb; }
    .ow-row-customer td:first-child { border-left-color: #7c3aed; }
    .ow-row-action td:first-child { border-left-color: #d97706; }
    .ow-row-issue td:first-child { border-left-color: #dc2626; }
    .ow-row-released td:first-child { border-left-color: #059669; }
    .ow-row-done td:first-child { border-left-color: #16a34a; }
    .ow-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .ow-l1 { font-weight: 600; }
    .ow-l2 { font-size: .78rem; color: var(--ow-muted); margin-top: .1rem; }
    .ow-l3 { font-size: .72rem; color: var(--ow-faint); margin-top: .1rem; }
    .ow-order-no { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; color: var(--ow-link); font-size: .8rem; }
    .ow-next { color: var(--ow-link); font-weight: 600; font-size: .8125rem; }
    .ow-next:hover { text-decoration: underline; }
    .ow-foot { display: flex; justify-content: space-between; gap: 1rem; padding: .65rem 1rem; color: var(--ow-muted); font-size: .75rem; border-top: 1px solid var(--ow-line); }
    .ow-flow { color: var(--ow-muted); font-size: .75rem; margin-top: .75rem; }
    .ow-empty { text-align: center; color: var(--ow-muted); padding: 2rem 1rem; }

    /* --- detail ------------------------------------------------------- */
    .ow-steps { display: flex; flex-wrap: wrap; gap: .35rem .5rem; align-items: center; margin: .9rem 0 .9rem; }
    .ow-step { display: inline-flex; align-items: center; gap: .35rem; font-size: .78rem; color: var(--ow-muted); }
    .ow-step-n { width: 1.35rem; height: 1.35rem; border-radius: 9999px; display: grid; place-items: center; font-size: .68rem; font-weight: 700; background: var(--ow-soft-2); color: var(--ow-muted); }
    .ow-step-done .ow-step-n { background: transparent; color: #16a34a; font-size: .85rem; }
    .ow-step-current { color: var(--ow-text); font-weight: 700; }
    .ow-step-current .ow-step-n { background: var(--ow-primary); color: var(--ow-primary-text); }
    .ow-step-issue .ow-step-n { background: #dc2626; color: #fff; }
    .ow-step-sep { color: var(--ow-faint); font-size: .75rem; }

    .ow-banner { display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap; border-radius: .75rem; padding: .85rem 1.1rem; border: 1px solid; }
    .ow-banner-title { font-weight: 700; }
    .ow-banner-text { font-size: .8rem; margin-top: .1rem; }
    .ow-banner-info { background: var(--ow-progress-bg); border-color: var(--ow-progress-bd); color: var(--ow-progress-fg); }
    .ow-banner-customer { background: var(--ow-customer-bg); border-color: var(--ow-customer-bd); color: var(--ow-customer-fg); }
    .ow-banner-warning { background: var(--ow-action-bg); border-color: var(--ow-action-bd); color: var(--ow-action-fg); }
    .ow-banner-danger { background: var(--ow-issue-bg); border-color: var(--ow-issue-bd); color: var(--ow-issue-fg); }
    .ow-banner-success { background: var(--ow-done-bg); border-color: var(--ow-done-bd); color: var(--ow-done-fg); }
    .ow-banner .ow-btn { color: inherit; border-color: currentColor; background: var(--ow-bg); }

    .ow-siblings { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-top: .75rem; font-size: .78rem; color: var(--ow-muted); }
    .ow-sibling { display: inline-flex; gap: .4rem; align-items: center; border: 1px solid var(--ow-line); border-radius: .5rem; padding: .3rem .55rem; background: var(--ow-bg); color: var(--ow-text); font-size: .75rem; }
    .ow-sibling-current { border-color: var(--ow-primary); box-shadow: inset 0 0 0 1px var(--ow-primary); }

    .ow-tabs { display: flex; gap: .25rem; flex-wrap: wrap; margin: 1.1rem 0 .9rem; }
    .ow-tab { border-radius: .5rem; padding: .45rem .8rem; font-size: .8125rem; font-weight: 600; color: var(--ow-muted); border: 0; background: none; cursor: pointer; }
    .ow-tab:hover { color: var(--ow-text); background: var(--ow-soft-2); }
    .ow-tab-active { background: var(--ow-soft-2); color: var(--ow-text); }

    .ow-callout { border-radius: .6rem; padding: .7rem .85rem; font-size: .8rem; border: 1px solid; }
    .ow-callout-warning { background: var(--ow-action-bg); border-color: var(--ow-action-bd); color: var(--ow-action-fg); }
    .ow-callout-info { background: var(--ow-progress-bg); border-color: var(--ow-progress-bd); color: var(--ow-progress-fg); }
    .ow-callout-success { background: var(--ow-done-bg); border-color: var(--ow-done-bd); color: var(--ow-done-fg); }
    .ow-callout-danger { background: var(--ow-issue-bg); border-color: var(--ow-issue-bd); color: var(--ow-issue-fg); }

    .ow-linked { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .7rem 0; border-top: 1px solid var(--ow-line-2); }
    .ow-linked:first-of-type { border-top: 0; }
    .ow-linked-extra { font-size: .75rem; }
    .ow-linked-files { margin-top: .75rem; padding-top: .65rem; border-top: 1px solid var(--ow-line-2); }

    /* payment summary card: release strip, payment history entries with slip / receipt thumbnails */
    .ow-due { color: var(--ow-action-fg); }
    .ow-release { display: flex; gap: .75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-top: .75rem; padding: .65rem .75rem; border: 1px solid var(--ow-action-bd); background: var(--ow-action-bg); border-radius: .6rem; }
    .ow-pay-head { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin-top: 1rem; padding-top: .75rem; border-top: 1px solid var(--ow-line); font-weight: 600; }
    .ow-pay { padding: .7rem 0; border-bottom: 1px solid var(--ow-line-2); }
    .ow-pay:last-child { border-bottom: 0; padding-bottom: 0; }
    .ow-pay-row { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
    .ow-pay-amount { font-weight: 700; font-variant-numeric: tabular-nums; }
    .ow-pay-meta { display: flex; flex-wrap: wrap; gap: .15rem .75rem; font-size: .72rem; color: var(--ow-muted); margin-top: .3rem; }
    .ow-pay-remarks { font-size: .75rem; margin-top: .25rem; overflow-wrap: anywhere; }
    .ow-pay-files { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .45rem; }
    .ow-page a.ow-pay-file, .ow-pay-file { display: inline-flex; align-items: center; gap: .45rem; max-width: 100%; padding: .25rem .55rem .25rem .25rem; border: 1px solid var(--ow-line); border-radius: .5rem; background: var(--ow-soft); color: var(--ow-link); font-size: .72rem; font-weight: 600; text-decoration: none; }
    .ow-page a.ow-pay-file:hover { border-color: var(--ow-faint); text-decoration: none; }
    .ow-pay-file img { flex: none; width: 2.75rem; height: 2.75rem; object-fit: cover; border-radius: .35rem; border: 1px solid var(--ow-line); background: var(--ow-bg); }
    .ow-pay-file-ext { flex: none; display: grid; place-items: center; width: 2.75rem; height: 2.75rem; border-radius: .35rem; background: var(--ow-soft-2); color: var(--ow-muted); font-size: .62rem; font-weight: 700; }
    .ow-pay-file-name { min-width: 0; max-width: 10rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ow-pay-file-missing { color: var(--ow-muted); cursor: default; }
    /* payment slips upload: small square tiles, 6 per row (3 on phones) */
    .ow-slip-upload .filepond--root[data-style-panel-layout='grid'] .filepond--item { width: calc(16.66% - .5rem); }
    @media (max-width: 767px) { .ow-slip-upload .filepond--root[data-style-panel-layout='grid'] .filepond--item { width: calc(33.33% - .5rem); } }
    .ow-slip-upload .filepond--file-info-main { font-size: .68rem; }
    .ow-slip-upload .filepond--file-info-sub, .ow-slip-upload .filepond--file-status-sub { display: none; }
    .ow-slip-upload .filepond--file-status-main { font-size: .62rem; }
    .ow-slip-upload .filepond--item { cursor: zoom-in; }
    /* Payment history heading: a clear clickable bar */
    .ow-doc-preview { display: flex; flex-direction: column; gap: .4rem; }
    .ow-doc-preview iframe { width: 100%; height: min(70vh, 44rem); border: 1px solid var(--ow-line, #e5e7eb); border-radius: .5rem; background: #fff; }
    .ow-doc-preview a { align-self: flex-end; font-size: .78rem; }
    .ow-doc-preview-empty { display: grid; place-items: center; height: 12rem; border: 1px dashed var(--ow-line, #e5e7eb); border-radius: .5rem; color: var(--ow-muted, #64748b); font-size: .85rem; }
    .ow-linked-list { position: relative; }
    .ow-linked-list.is-scrolling { overflow-y: auto; padding-right: .35rem; border-bottom: 1px solid var(--ow-line); }
    .ow-pay-toggle { width: 100%; align-items: center; margin-top: 1rem; padding: .7rem .9rem; border: 1px solid var(--ow-line); border-radius: .6rem; background: var(--ow-soft); cursor: pointer; text-align: left; color: inherit; font-family: inherit; font-size: .95rem; font-weight: 700; transition: background .15s ease, border-color .15s ease; }
    .ow-pay-toggle:hover { background: var(--ow-soft-2, var(--ow-soft)); border-color: var(--ow-faint, var(--ow-line)); }
    .ow-pay-toggle > span:first-child { display: inline-flex; align-items: center; gap: .5rem; }
    .ow-pay-toggle > span:first-child::before { content: ''; width: 1.1rem; height: 1.1rem; flex: none; background: currentColor; -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 12a9 9 0 1 0 3-6.7L3 8'/%3E%3Cpath d='M3 3v5h5'/%3E%3Cpath d='M12 7v5l3 2'/%3E%3C/svg%3E") center / contain no-repeat; mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M3 12a9 9 0 1 0 3-6.7L3 8'/%3E%3Cpath d='M3 3v5h5'/%3E%3Cpath d='M12 7v5l3 2'/%3E%3C/svg%3E") center / contain no-repeat; opacity: .7; }
    .ow-pay-toggle .ow-note { font-size: .8rem; font-weight: 500; }
    .ow-pay-toggle .ow-note { display: inline-flex; align-items: center; gap: .35rem; }
    .ow-pay-toggle-label { color: var(--ow-link); font-weight: 600; }
    .ow-pay-chevron { width: 1rem; height: 1rem; color: var(--ow-muted); transition: transform .15s ease; }
    .ow-pay-chevron.is-open { transform: rotate(180deg); }
    .ow-file-viewer { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 1rem; background: rgb(15 23 42 / .72); }
    .ow-file-viewer-box { display: flex; flex-direction: column; width: min(56rem, 100%); max-height: calc(100vh - 2rem); border-radius: .75rem; overflow: hidden; background: #fff; box-shadow: 0 20px 50px rgb(0 0 0 / .35); }
    .dark .ow-file-viewer-box { background: #111827; }
    .ow-file-viewer-head { display: flex; align-items: center; gap: .75rem; padding: .6rem .9rem; border-bottom: 1px solid var(--ow-line, #e5e7eb); }
    .ow-file-viewer-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; font-size: .85rem; }
    .ow-file-viewer-link { font-size: .78rem; font-weight: 600; color: var(--ow-link, #1d4ed8); white-space: nowrap; }
    .ow-file-viewer-close { font-size: 1.4rem; line-height: 1; padding: 0 .25rem; color: var(--ow-muted, #64748b); }
    .ow-file-viewer-body { flex: 1; min-height: 0; display: grid; place-items: center; background: var(--ow-soft, #f8fafc); }
    .ow-file-viewer-body img { max-width: 100%; max-height: calc(100vh - 6rem); object-fit: contain; }
    .ow-file-viewer-body iframe { width: 100%; height: calc(100vh - 6rem); border: 0; background: #fff; }

    /* Add / Edit payment modal notes (Filament modal content) */
    .ow-modal-warning { border: 1px solid #fde68a; background: #fffbeb; color: #92400e; border-radius: .5rem; padding: .6rem .75rem; font-size: .8125rem; line-height: 1.45; }
    .ow-modal-warning ul { margin: .35rem 0 0 1.1rem; padding: 0; list-style: disc; }
    .ow-modal-note { margin-top: .5rem; font-size: .78rem; color: #64748b; }
    .dark .ow-modal-warning { border-color: rgb(245 158 11 / .35); background: rgb(146 64 14 / .25); color: #fcd34d; }
    .dark .ow-modal-note { color: #94a3b8; }

    .ow-total-row { display: flex; justify-content: space-between; align-items: center; font-weight: 700; padding-top: .65rem; margin-top: .4rem; border-top: 1px solid var(--ow-line); }

    .ow-price-table { width: 100%; border-collapse: collapse; }
    .ow-price-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ow-muted); text-align: left; padding: .45rem .5rem; border-bottom: 1px solid var(--ow-line); white-space: nowrap; }
    .ow-price-table td { padding: .5rem; vertical-align: top; border-bottom: 1px solid var(--ow-line-2); }
    .ow-price-hint { font-size: .68rem; color: var(--ow-muted); margin-top: .15rem; white-space: nowrap; }
    .ow-price-diff { color: #b45309; }
    .dark .ow-page .ow-price-diff { color: #fcd34d; }
    .ow-money { position: relative; }
    .ow-money > span { position: absolute; left: .55rem; top: 50%; transform: translateY(-50%); font-size: .72rem; color: var(--ow-muted); }
    .ow-money > .ow-input { padding-left: 2rem; text-align: right; font-variant-numeric: tabular-nums; }

    .ow-page .ow-timeline { display: block; list-style: none; margin: 0; padding: 0 .25rem 0 0; max-height: 33rem; overflow-y: auto; }
    .ow-page .ow-timeline li { display: block; padding: .6rem 0; border-bottom: 1px solid var(--ow-line-2); }
    .ow-page .ow-timeline li::before { content: none; }
    .ow-page .ow-timeline li:last-child { border-bottom: 0; }
    .ow-page .ow-timeline .ow-tl-sub { white-space: nowrap; }
    .ow-activity-filter { display: flex; flex-wrap: wrap; gap: .5rem; align-items: flex-end; margin-bottom: .75rem; }
    .ow-activity-filter .ow-field { width: 11rem; }
    .ow-tl-title { font-weight: 600; font-size: .85rem; overflow-wrap: anywhere; }
    .ow-tl-sub { font-size: .72rem; color: var(--ow-muted); }
    .ow-tl-price .ow-tl-title { color: var(--ow-link); }
    .ow-tl-offer { margin: .35rem 0 .3rem; max-width: 46rem; overflow-x: auto; }
    .ow-tl-offer table { width: 100%; border-collapse: collapse; font-size: .78rem; }
    .ow-tl-offer th { text-align: left; font-weight: 600; font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; color: var(--ow-muted); padding: .2rem .5rem; border-bottom: 1px solid var(--ow-line); }
    .ow-tl-offer td { padding: .25rem .5rem; border-bottom: 1px solid var(--ow-line-2, var(--ow-line)); vertical-align: top; }
    .ow-tl-offer .num { text-align: right; white-space: nowrap; }
    .ow-tl-offer tfoot td { font-weight: 700; border-bottom: 0; }
    .ow-tl-dest { color: var(--ow-muted); }
    .ow-tl-issue .ow-tl-title { color: #b91c1c; }
    .dark .ow-page .ow-tl-issue .ow-tl-title { color: #fca5a5; }

    .ow-offers { display: grid; gap: .75rem; max-height: 32rem; overflow-y: auto; padding-right: .25rem; }
    .ow-offer { border: 1px solid var(--ow-line); border-radius: .6rem; padding: .7rem .85rem; background: var(--ow-soft); }
    .ow-offer-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .ow-offer-meta { display: flex; flex-wrap: wrap; gap: .4rem; align-items: center; }
    .ow-offer-chip { font-size: .72rem; padding: .1rem .45rem; border-radius: .35rem; background: var(--ow-bg); border: 1px solid var(--ow-line); color: var(--ow-text); }
    .ow-offer-total { font-weight: 700; font-size: 1rem; font-variant-numeric: tabular-nums; }
    .ow-offer-reason { margin-top: .5rem; font-size: .8rem; color: var(--ow-issue-fg); background: var(--ow-issue-bg); border: 1px solid var(--ow-issue-bd); border-radius: .45rem; padding: .4rem .6rem; }
    .ow-offer-lines { width: 100%; border-collapse: collapse; margin-top: .55rem; font-size: .8rem; }
    .ow-offer-lines th { font-size: .66rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ow-muted); text-align: left; padding: .3rem .4rem; border-bottom: 1px solid var(--ow-line); }
    .ow-offer-lines td { padding: .35rem .4rem; border-bottom: 1px solid var(--ow-line-2); }
    .ow-offer-lines th.ow-num, .ow-offer-lines td.ow-num { text-align: right; }

    .ow-doc { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: .8rem 0; border-top: 1px solid var(--ow-line-2); }
    .ow-doc:first-of-type { border-top: 0; }

    .ow-radio-group { display: inline-flex; border: 1px solid var(--ow-line); border-radius: .5rem; overflow: hidden; }
    .ow-radio-group label { padding: .4rem .8rem; font-size: .8rem; cursor: pointer; border-right: 1px solid var(--ow-line); color: var(--ow-muted); margin: 0; }
    .ow-radio-group label:last-child { border-right: 0; }
    .ow-radio-group input { display: none; }
    .ow-radio-group label:has(input:checked) { background: var(--ow-primary); color: var(--ow-primary-text); }

    /* --- create order ------------------------------------------------- */
    .ow-pair { position: relative; }
    .ow-pair-head { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: .85rem; }
    .ow-party { border: 1px solid var(--ow-line-2); border-radius: .6rem; padding: .85rem; background: var(--ow-soft); }
    .ow-party-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: var(--ow-muted); font-weight: 700; margin-bottom: .6rem; }
    .ow-party .ow-field + .ow-field { margin-top: .6rem; }
    .ow-products th:first-child, .ow-products td:first-child { width: 42%; }
    .ow-footer-bar { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: .9rem 0 .25rem; }
    .ow-footer-meta { color: var(--ow-muted); font-size: .8rem; }
</style>

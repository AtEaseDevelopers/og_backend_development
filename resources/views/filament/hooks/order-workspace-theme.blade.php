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
    .ow-stack { display: grid; gap: 1rem; }
    .ow-grid-main { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr); align-items: start; }
    .ow-grid-2 { display: grid; gap: 1rem; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 1023px) { .ow-grid-main, .ow-grid-2 { grid-template-columns: minmax(0, 1fr); } }
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
    .ow-intake { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .ow-intake-title { font-weight: 600; }
    .ow-intake-flow { font-size: .75rem; color: var(--ow-muted); margin-top: .15rem; }
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
    .ow-legend { display: flex; flex-wrap: wrap; gap: .4rem; }

    .ow-filters { padding: .85rem 1rem 1rem; }
    .ow-filter-top { display: flex; gap: .75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-bottom: .75rem; }
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
    .ow-search { max-width: 22rem; }
    .ow-field label, .ow-label { display: block; font-size: .72rem; color: var(--ow-muted); margin-bottom: .25rem; }
    .ow-label .ow-req, .ow-field label .ow-req { color: #dc2626; }
    .ow-fgrid { display: grid; gap: .75rem 1rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .ow-fgrid + .ow-fgrid { margin-top: .75rem; }
    @media (max-width: 900px) { .ow-fgrid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ow-field-error { color: #dc2626; font-size: .72rem; margin-top: .2rem; }

    .ow-table-wrap { overflow-x: auto; }
    .ow-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .ow-table th { text-align: left; font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ow-muted); font-weight: 600; background: var(--ow-soft-2); padding: .6rem .75rem; border-bottom: 1px solid var(--ow-line); white-space: nowrap; }
    .ow-table td { padding: .75rem; border-bottom: 1px solid var(--ow-line-2); vertical-align: top; }
    .ow-table tbody tr:last-child td { border-bottom: 0; }
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

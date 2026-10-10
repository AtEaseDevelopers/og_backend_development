{{--
    One datatable header for every list page, the same as the Orders table (.ow-table th in order-workspace-theme):
    grey header row, small uppercase grey labels, ↕ / ▲ / ▼ sort marks, the Excel-style funnel next to the label
    (public/js/og/excel-filter.js) and the column toggle at the right end of the header row.
    Applies to Filament tables (.fi-ta-table) and custom-built tables marked data-og-xtable.
--}}
<style>
    .fi-ta-table > thead > tr > th,
    table[data-og-xtable] > thead > tr > th {
        background: #f1f5f9 !important;
        color: #64748b !important;
        font-size: .68rem !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: .05em !important;
        line-height: 1.3;
        padding-top: .6rem !important;
        padding-bottom: .6rem !important;
        border-bottom: 1px solid #e5e7eb !important;
        white-space: nowrap;
        vertical-align: middle;
    }
    .dark .fi-ta-table > thead > tr > th,
    .dark table[data-og-xtable] > thead > tr > th {
        background: #1f2937 !important;
        color: #94a3b8 !important;
        border-bottom-color: #374151 !important;
    }
    .fi-ta-table > thead > tr > th .fi-ta-header-cell-label { font: inherit !important; color: inherit !important; text-transform: inherit; letter-spacing: inherit; }
    .fi-ta-table > thead > tr > th[aria-sort],
    table[data-og-xtable] > thead > tr > th[aria-sort] { color: #0f172a !important; }
    .dark .fi-ta-table > thead > tr > th[aria-sort],
    .dark table[data-og-xtable] > thead > tr > th[aria-sort] { color: #f1f5f9 !important; }

    /* sort marks as on Orders: ↕ when sortable, ▲ / ▼ when sorted (Filament's chevrons hidden) */
    .fi-ta-table > thead > tr > th .fi-ta-header-cell-sort-icon { display: none !important; }
    .fi-ta-table > thead > tr > th span[role="button"] .fi-ta-header-cell-label::after { content: '↕'; margin-left: .3rem; font-size: .62rem; opacity: .35; }
    .fi-ta-table > thead > tr > th[aria-sort="ascending"] .fi-ta-header-cell-label::after { content: '▲'; opacity: 1; }
    .fi-ta-table > thead > tr > th[aria-sort="descending"] .fi-ta-header-cell-label::after { content: '▼'; opacity: 1; }
    /* custom tables: the mark is a span added by excel-filter.js */
    table[data-og-xtable] > thead > tr > th.og-xt-sortable { cursor: pointer; }
    .og-xt-sort { margin-left: .3rem; font-size: .62rem; opacity: .35; }
    .og-xt-sort.is-sorted { opacity: 1; }

    /* column toggle moved into the last header cell (excel-filter.js) */
    .og-col-toggle-cell { text-align: right !important; }
    .og-col-toggle-cell .og-ft-toggle, .og-col-toggle-cell .og-xt-toggle { margin-left: .35rem; vertical-align: middle; }
    /* Filament's own column toggle above the table is replaced by the one in the header row */
    .fi-ta-col-toggle.og-native-toggle { display: none !important; }
    /* a toolbar left with nothing in it (its only item was the column toggle) is hidden */
    .fi-ta-header-ctn.og-toolbar-empty { display: none !important; }
</style>

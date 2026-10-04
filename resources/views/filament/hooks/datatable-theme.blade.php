<style>
    /*
     * Global datatable convention: "Showing x to y of z" on the left, per-page selector and
     * page numbers together on the right. Filament renders a 3-column grid that centres the
     * per-page selector, so switch the bar to a flex row.
     */
    nav.fi-pagination {
        display: flex !important;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 0.75rem;
    }

    nav.fi-pagination > * {
        grid-column: auto !important;
        justify-self: auto !important;
    }

    nav.fi-pagination > .fi-pagination-previous-btn { order: 0; }
    nav.fi-pagination > .fi-pagination-overview { order: 1; margin-inline-end: auto; }
    nav.fi-pagination > div:has(.fi-pagination-records-per-page-select) { order: 2; margin-inline-start: auto; }
    nav.fi-pagination > .fi-pagination-items { order: 3; }
    nav.fi-pagination > .fi-pagination-next-btn { order: 4; }

    /* when the overview is present it already pushes the rest right */
    nav.fi-pagination > .fi-pagination-overview ~ div:has(.fi-pagination-records-per-page-select) {
        margin-inline-start: 0;
    }

    /* Table header row: search on the left, filters + column toggle on the right. */
    .fi-ta-header-toolbar > .ms-auto {
        margin-inline-start: 0 !important;
        flex: 1 1 auto;
    }

    .fi-ta-header-toolbar > .ms-auto > .fi-ta-search-field {
        margin-inline-end: auto;
        min-width: 16rem;
    }

    /* only the search box pushes; filters + column toggle stay grouped on the right */
    .fi-ta-header-toolbar > .ms-auto > .fi-ta-search-field ~ * {
        margin-inline-start: 0 !important;
    }

    /* CSN tables: the Number column stays fixed while scrolling horizontally. */
    .fi-page-order-operations .fi-ta-table thead th:first-child,
    .fi-page-order-operations .fi-ta-table tbody td:first-child,
    .fi-resource-consignment-notes .fi-ta-table thead th:first-child,
    .fi-resource-consignment-notes .fi-ta-table tbody td:first-child {
        position: sticky;
        left: 0;
        z-index: 2;
        background-color: inherit;
        box-shadow: 1px 0 0 rgb(229 231 235);
    }

    .fi-page-order-operations .fi-ta-table thead th:first-child,
    .fi-resource-consignment-notes .fi-ta-table thead th:first-child {
        z-index: 3;
        background-color: rgb(249 250 251);
    }

    .fi-page-order-operations .fi-ta-table tbody tr,
    .fi-resource-consignment-notes .fi-ta-table tbody tr {
        background-color: #fff;
    }

    .fi-page-order-operations .fi-ta-table tbody tr:nth-child(even),
    .fi-resource-consignment-notes .fi-ta-table tbody tr:nth-child(even) {
        background-color: rgb(249 250 251);
    }

    .fi-page-order-operations .fi-ta-table tbody tr:hover,
    .fi-resource-consignment-notes .fi-ta-table tbody tr:hover {
        background-color: rgb(243 244 246);
    }

    /* Dark mode: the sticky column must use the dark row colours, or white text disappears. */
    .dark .fi-page-order-operations .fi-ta-table thead th:first-child,
    .dark .fi-resource-consignment-notes .fi-ta-table thead th:first-child {
        background-color: rgb(17 24 39);
        box-shadow: 1px 0 0 rgb(55 65 81);
    }

    .dark .fi-page-order-operations .fi-ta-table tbody td:first-child,
    .dark .fi-resource-consignment-notes .fi-ta-table tbody td:first-child {
        box-shadow: 1px 0 0 rgb(55 65 81);
    }

    .dark .fi-page-order-operations .fi-ta-table tbody tr,
    .dark .fi-resource-consignment-notes .fi-ta-table tbody tr {
        background-color: rgb(17 24 39);
    }

    .dark .fi-page-order-operations .fi-ta-table tbody tr:nth-child(even),
    .dark .fi-resource-consignment-notes .fi-ta-table tbody tr:nth-child(even) {
        background-color: rgb(24 32 48);
    }

    .dark .fi-page-order-operations .fi-ta-table tbody tr:hover,
    .dark .fi-resource-consignment-notes .fi-ta-table tbody tr:hover {
        background-color: rgb(31 41 55);
    }

    /*
     * Confirmation modals use a centred two-column grid rendered as [Confirm, Cancel].
     * Flip the reading direction of the row so Cancel is on the left and Confirm on the right,
     * keeping the equal-width buttons.
     */
    .fi-modal-footer-actions.sm\:grid {
        direction: rtl;
    }

    .fi-modal-footer-actions.sm\:grid > * {
        direction: ltr;
    }

    /* Dropdown filter panels never grow past the viewport: scroll inside instead of clipping the top. */
    .fi-dropdown-panel .fi-ta-filters {
        max-height: min(70vh, 640px);
        overflow-y: auto;
    }
</style>

<style>
    .fi-resource-payments.fi-resource-list-records-page .fi-header-heading {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-payments.fi-resource-list-records-page .fi-header-subheading {
        display: none;
    }

    .fi-resource-payments.fi-resource-list-records-page .fi-header-actions .fi-btn {
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-filter-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-filter-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-filter-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-filter-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-search-wrap {
        position: relative;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-filter-input-search {
        padding-left: 2.25rem;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-filter-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        justify-content: flex-end;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-btn {
        border-radius: 0.5rem;
        min-height: 2.375rem;
        padding: 0.4375rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-btn-primary {
        border: none;
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-btn-secondary {
        border: 1px solid rgb(209 213 219);
        background: white;
        color: rgb(55 65 81);
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        overflow: hidden;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-ta-ctn {
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-ta-header-ctn {
        display: none;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-ta-table thead th {
        background: rgb(249 250 251);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-ta-table tbody td {
        font-size: 0.875rem;
        color: rgb(17 24 39);
        vertical-align: middle;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-ta-cell-payment {
        font-variant-numeric: tabular-nums;
        font-weight: 600;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-badge {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-ta-footer {
        border-top: 1px solid rgb(243 244 246);
        background: rgb(249 250 251);
        padding: 0.75rem 1rem;
    }

    .fi-resource-payments.fi-resource-list-records-page .pl-list-panel .fi-ta-pagination {
        gap: 0.375rem;
    }

    @media (max-width: 1023px) {
        .fi-resource-payments.fi-resource-list-records-page .pl-filter-grid {
            grid-template-columns: 1fr;
        }

        .fi-resource-payments.fi-resource-list-records-page .pl-filter-actions {
            justify-content: stretch;
        }

        .fi-resource-payments.fi-resource-list-records-page .pl-filter-actions .pl-btn {
            flex: 1;
        }
    }
</style>

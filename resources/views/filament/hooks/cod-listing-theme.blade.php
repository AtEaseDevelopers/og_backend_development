<style>
    .fi-page-cod-listing .fi-header-heading {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-page-cod-listing .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        max-width: 42rem;
    }

    .fi-page-cod-listing .cl-page-toolbar {
        display: flex;
        justify-content: flex-end;
        margin-top: -0.5rem;
        margin-bottom: 1rem;
    }

    .fi-page-cod-listing .cl-date-field {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.75rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        background: white;
    }

    .fi-page-cod-listing .cl-date-label {
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(55 65 81);
    }

    .fi-page-cod-listing .cl-date-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }

    .fi-page-cod-listing .cl-date-display {
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-page-cod-listing .cl-filter-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .fi-page-cod-listing .cl-filter-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) auto;
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-page-cod-listing .cl-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-page-cod-listing .cl-filter-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-page-cod-listing .cl-filter-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-page-cod-listing .cl-search-wrap {
        position: relative;
    }

    .fi-page-cod-listing .cl-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-page-cod-listing .cl-filter-input-search {
        padding-left: 2.25rem;
    }

    .fi-page-cod-listing .cl-filter-actions {
        display: flex;
        gap: 0.5rem;
        align-items: center;
    }

    .fi-page-cod-listing .cl-btn {
        border-radius: 0.5rem;
        min-height: 2.375rem;
        padding: 0.4375rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .fi-page-cod-listing .cl-btn-primary {
        border: none;
        background: rgb(15 23 42);
        color: white;
    }

    .fi-page-cod-listing .cl-btn-secondary {
        border: 1px solid rgb(209 213 219);
        background: white;
        color: rgb(55 65 81);
    }

    .fi-page-cod-listing .cl-list-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        overflow: hidden;
    }

    .fi-page-cod-listing .cl-table-wrap {
        overflow-x: auto;
    }

    .fi-page-cod-listing .cl-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-page-cod-listing .cl-table th {
        padding: 0.75rem 1rem;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        background: rgb(249 250 251);
        border-bottom: 1px solid rgb(229 231 235);
    }

    .fi-page-cod-listing .cl-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        vertical-align: middle;
        color: rgb(17 24 39);
    }

    .fi-page-cod-listing .cl-table tbody tr:last-child td {
        border-bottom: none;
    }

    .fi-page-cod-listing .cl-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .fi-page-cod-listing .cl-amount {
        font-weight: 600;
    }

    .fi-page-cod-listing .cl-mono {
        font-weight: 600;
    }

    .fi-page-cod-listing .cl-action-col {
        width: 4rem;
        text-align: center;
    }

    .fi-page-cod-listing .cl-status-pill {
        display: inline-flex;
        border-radius: 9999px;
        padding: 0.1875rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .fi-page-cod-listing .cl-status-in-progress {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .fi-page-cod-listing .cl-status-completed {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-page-cod-listing .cl-view-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 9999px;
        color: rgb(55 65 81);
        background: white;
    }

    .fi-page-cod-listing .cl-view-btn svg {
        width: 1rem;
        height: 1rem;
    }

    .fi-page-cod-listing .cl-empty {
        padding: 2rem 1rem !important;
        text-align: center;
        color: rgb(107 114 128);
    }

    .fi-page-cod-listing .cl-table-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.875rem 1rem;
        border-top: 1px solid rgb(243 244 246);
        background: rgb(249 250 251);
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    @media (max-width: 1023px) {
        .fi-page-cod-listing .cl-filter-grid {
            grid-template-columns: 1fr;
        }

        .fi-page-cod-listing .cl-page-toolbar {
            justify-content: flex-start;
        }
    }
</style>

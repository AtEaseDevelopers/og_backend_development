<style>
    .fi-page-cash-bill .fi-header-heading {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
    }

    .fi-page-cash-bill .fi-header-subheading {
        color: rgb(107 114 128);
        font-size: 0.9375rem;
        max-width: 42rem;
    }

    .cb-page {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .cb-intro {
        margin-top: -0.25rem;
    }

    .cb-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .cb-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 0.375rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .cb-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .cb-badge-date {
        background: rgb(249 250 251);
        color: rgb(107 114 128);
        border: 1px solid rgb(229 231 235);
    }

    .cb-card {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: white;
        overflow: hidden;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
    }

    .cb-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.125rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .cb-card-head-split {
        align-items: flex-start;
    }

    .cb-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .cb-card-note {
        max-width: 20rem;
        text-align: right;
        font-size: 0.75rem;
        color: rgb(107 114 128);
        line-height: 1.45;
    }

    .cb-search-wrap {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .cb-search-field {
        position: relative;
        width: 16rem;
        max-width: 100%;
    }

    .cb-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
    }

    .cb-search-input {
        width: 100%;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem 0.5rem 2.25rem;
        font-size: 0.875rem;
        background: white;
    }

    .cb-search-input:focus {
        outline: none;
        border-color: rgb(15 23 42);
        box-shadow: 0 0 0 1px rgb(15 23 42);
    }

    .cb-icon-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        color: rgb(75 85 99);
    }

    .cb-icon-btn svg {
        width: 1.125rem;
        height: 1.125rem;
    }

    .cb-search-results {
        border-bottom: 1px solid rgb(243 244 246);
        background: rgb(249 250 251);
    }

    .cb-search-result {
        display: flex;
        width: 100%;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.125rem;
        padding: 0.625rem 1.125rem;
        text-align: left;
        border-bottom: 1px solid rgb(243 244 246);
        background: transparent;
    }

    .cb-search-result:hover {
        background: white;
    }

    .cb-search-result-csn {
        font-size: 0.8125rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .cb-search-result-meta {
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .cb-table-wrap {
        overflow-x: auto;
    }

    .cb-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .cb-table th {
        padding: 0.625rem 1rem;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        background: rgb(249 250 251);
        border-bottom: 1px solid rgb(229 231 235);
    }

    .cb-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        vertical-align: middle;
        color: rgb(17 24 39);
    }

    .cb-table tbody tr:last-child td {
        border-bottom: none;
    }

    .cb-num {
        text-align: right;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    .cb-action-col {
        width: 3rem;
        text-align: center;
    }

    .cb-mono {
        font-weight: 600;
    }

    .cb-status-pill {
        display: inline-flex;
        border-radius: 9999px;
        background: rgb(243 244 246);
        padding: 0.125rem 0.5rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        color: rgb(75 85 99);
    }

    .cb-remove-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: rgb(220 38 38);
    }

    .cb-remove-btn svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .cb-empty {
        padding: 2rem 1rem !important;
        text-align: center;
        color: rgb(107 114 128);
    }

    .cb-table-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.875rem 1.125rem;
        background: rgb(249 250 251);
        border-top: 1px solid rgb(229 231 235);
        font-size: 0.875rem;
        color: rgb(55 65 81);
    }

    .cb-footer-total {
        display: flex;
        align-items: baseline;
        gap: 0.75rem;
    }

    .cb-footer-total-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .cb-lcd {
        font-family: "Courier New", Courier, monospace;
        font-weight: 700;
        letter-spacing: 0.06em;
        font-variant-numeric: tabular-nums;
    }

    .cb-lcd-dark {
        color: rgb(17 24 39);
        font-size: 1.5rem;
    }

    .cb-lcd-green {
        color: rgb(22 163 74);
    }

    .cb-lcd-lg {
        font-size: 1.75rem;
    }

    .cb-lcd-sm {
        font-size: 1rem;
    }

    .cb-payment-grid {
        display: grid;
        grid-template-columns: 1.1fr 0.9fr;
        gap: 1.25rem;
        padding: 1.125rem;
    }

    .cb-section-label {
        margin-bottom: 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .cb-method-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.625rem;
    }

    .cb-method-btn {
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.75rem 0.875rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(55 65 81);
        text-align: center;
    }

    .cb-method-btn-wide {
        grid-column: 1 / -1;
        border-style: dashed;
        color: rgb(107 114 128);
    }

    .cb-method-btn-active {
        border-color: rgb(15 23 42);
        border-width: 2px;
        color: rgb(15 23 42);
        box-shadow: inset 0 0 0 1px rgb(15 23 42);
    }

    .cb-payment-amounts {
        display: grid;
        gap: 1rem;
    }

    .cb-amount-block {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        padding: 0.875rem 1rem;
        background: rgb(249 250 251);
    }

    .cb-lcd-input {
        width: 100%;
        border: none;
        background: transparent;
        text-align: right;
        font-family: "Courier New", Courier, monospace;
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        color: rgb(17 24 39);
    }

    .cb-lcd-input:focus {
        outline: none;
    }

    .cb-settlement-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.125rem;
        background: rgb(249 250 251);
        border-top: 1px solid rgb(229 231 235);
    }

    .cb-settlement-math {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .cb-math-item {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .cb-math-label {
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .cb-math-symbol {
        font-size: 1.125rem;
        font-weight: 700;
        color: rgb(156 163 175);
    }

    .cb-settlement-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .cb-change-block {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        padding-right: 0.75rem;
        border-right: 1px solid rgb(229 231 235);
    }

    .cb-change-label {
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .cb-btn {
        border-radius: 0.5rem;
        padding: 0.625rem 1rem;
        font-size: 0.8125rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .cb-btn-outline {
        border: 1px solid rgb(209 213 219);
        background: white;
        color: rgb(55 65 81);
    }

    .cb-btn-primary {
        border: none;
        background: rgb(22 101 52);
        color: white;
    }

    .cb-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .cb-print-only {
        display: none;
    }

    @media (max-width: 1023px) {
        .cb-card-head,
        .cb-card-head-split {
            flex-direction: column;
            align-items: stretch;
        }

        .cb-card-note {
            text-align: left;
        }

        .cb-payment-grid,
        .cb-settlement-bar {
            grid-template-columns: 1fr;
            flex-direction: column;
            align-items: stretch;
        }

        .cb-settlement-actions,
        .cb-change-block {
            align-items: stretch;
            border-right: none;
            padding-right: 0;
        }
    }

    @media print {
        .fi-sidebar,
        .fi-topbar,
        .fi-header,
        .cb-card,
        .cb-intro {
            display: none !important;
        }

        .cb-print-only {
            display: block !important;
        }
    }
</style>

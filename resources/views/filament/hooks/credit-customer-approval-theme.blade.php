<style>
    .fi-page-credit-customer-approval .fi-header-heading {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-page-credit-customer-approval .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        max-width: 48rem;
    }

    .fi-page-credit-customer-approval .cca-layout {
        display: grid;
        grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr);
        gap: 1rem;
        align-items: stretch;
        min-height: calc(100vh - 11rem);
    }

    .fi-page-credit-customer-approval .cca-list-panel,
    .fi-page-credit-customer-approval .cca-detail-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
    }

    .fi-page-credit-customer-approval .cca-list-panel {
        padding: 1rem 1.125rem;
        display: flex;
        flex-direction: column;
    }

    .fi-page-credit-customer-approval .cca-detail-panel {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .fi-page-credit-customer-approval .cca-list-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.875rem;
    }

    .fi-page-credit-customer-approval .cca-panel-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-page-credit-customer-approval .cca-list-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-page-credit-customer-approval .cca-filter-panel {
        margin-bottom: 0.875rem;
        padding: 0.875rem;
        border: 1px solid rgb(243 244 246);
        border-radius: 0.625rem;
        background: rgb(249 250 251);
    }

    .fi-page-credit-customer-approval .cca-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.625rem;
        margin-bottom: 0.625rem;
    }

    .fi-page-credit-customer-approval .cca-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        min-width: 0;
    }

    .fi-page-credit-customer-approval .cca-filter-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-page-credit-customer-approval .cca-filter-input {
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.4375rem 0.625rem;
        font-size: 0.8125rem;
        background: white;
        min-width: 0;
    }

    .fi-page-credit-customer-approval .cca-table-wrap {
        overflow-x: auto;
        flex: 1;
    }

    .fi-page-credit-customer-approval .cca-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .fi-page-credit-customer-approval .cca-table th {
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        padding: 0.625rem 0.5rem;
        border-bottom: 1px solid rgb(229 231 235);
        white-space: nowrap;
    }

    .fi-page-credit-customer-approval .cca-table td {
        padding: 0.875rem 0.5rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(17 24 39);
        vertical-align: middle;
    }

    .fi-page-credit-customer-approval .cca-col-amount {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .fi-page-credit-customer-approval .cca-row {
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .fi-page-credit-customer-approval .cca-row:hover {
        background: rgb(249 250 251);
    }

    .fi-page-credit-customer-approval .cca-row-selected {
        background: rgb(243 244 246);
    }

    .fi-page-credit-customer-approval .cca-customer-name {
        font-weight: 700;
    }

    .fi-page-credit-customer-approval .cca-status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.25rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .fi-page-credit-customer-approval .cca-status-badge-lg {
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-page-credit-customer-approval .cca-status-blue { background: rgb(219 234 254); color: rgb(29 78 216); }
    .fi-page-credit-customer-approval .cca-status-approved { background: rgb(220 252 231); color: rgb(21 128 61); }
    .fi-page-credit-customer-approval .cca-status-danger { background: rgb(254 226 226); color: rgb(185 28 28); }
    .fi-page-credit-customer-approval .cca-status-gray { background: rgb(243 244 246); color: rgb(75 85 99); }

    .fi-page-credit-customer-approval .cca-empty,
    .fi-page-credit-customer-approval .cca-detail-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: rgb(107 114 128);
    }

    .fi-page-credit-customer-approval .cca-detail-empty {
        padding: 2rem 1.125rem;
    }

    .fi-page-credit-customer-approval .cca-detail-empty p {
        margin: 0.5rem 0 0;
        font-size: 0.875rem;
    }

    .fi-page-credit-customer-approval .cca-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 0.875rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgb(243 244 246);
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .fi-page-credit-customer-approval .cca-pagination-nav {
        display: flex;
        gap: 0.375rem;
    }

    .fi-page-credit-customer-approval .cca-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.375rem;
        background: white;
        color: rgb(107 114 128);
        cursor: pointer;
    }

    .fi-page-credit-customer-approval .cca-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .fi-page-credit-customer-approval .cca-detail-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 1.125rem;
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }

    .fi-page-credit-customer-approval .cca-detail-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .fi-page-credit-customer-approval .cca-detail-name {
        margin: 0;
        font-size: 1.0625rem;
        font-weight: 700;
        color: rgb(17 24 39);
        line-height: 1.3;
    }

    .fi-page-credit-customer-approval .cca-detail-reg {
        margin: 0.25rem 0 0;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-page-credit-customer-approval .cca-summary-card {
        border: 1px solid rgb(243 244 246);
        border-radius: 0.625rem;
        padding: 0.875rem 1rem;
        background: rgb(249 250 251);
    }

    .fi-page-credit-customer-approval .cca-summary-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .fi-page-credit-customer-approval .cca-summary-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        margin-bottom: 0.375rem;
    }

    .fi-page-credit-customer-approval .cca-summary-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: rgb(17 24 39);
        line-height: 1.1;
    }

    .fi-page-credit-customer-approval .cca-credit-score {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        color: rgb(22 163 74);
    }

    .fi-page-credit-customer-approval .cca-credit-score svg {
        width: 1rem;
        height: 1rem;
    }

    .fi-page-credit-customer-approval .cca-section-title {
        display: flex;
        align-items: center;
        gap: 0.375rem;
        margin: 0 0 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        color: rgb(107 114 128);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .fi-page-credit-customer-approval .cca-section-title svg {
        width: 0.9375rem;
        height: 0.9375rem;
    }

    .fi-page-credit-customer-approval .cca-doc-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-page-credit-customer-approval .cca-doc-item {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        padding: 0.625rem 0.75rem;
        background: white;
    }

    .fi-page-credit-customer-approval .cca-doc-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        font-size: 0.5625rem;
        font-weight: 800;
        letter-spacing: 0.02em;
        flex-shrink: 0;
    }

    .fi-page-credit-customer-approval .cca-doc-icon-pdf {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-page-credit-customer-approval .cca-doc-icon-csv {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .fi-page-credit-customer-approval .cca-doc-name {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-page-credit-customer-approval .cca-doc-size {
        font-size: 0.75rem;
        color: rgb(107 114 128);
        margin-top: 0.125rem;
    }

    .fi-page-credit-customer-approval .cca-notes-box {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        padding: 0.75rem;
        background: rgb(249 250 251);
        font-size: 0.8125rem;
        color: rgb(55 65 81);
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .fi-page-credit-customer-approval .cca-audit-trail {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        padding-left: 0.125rem;
    }

    .fi-page-credit-customer-approval .cca-audit-item {
        display: flex;
        gap: 0.625rem;
        position: relative;
    }

    .fi-page-credit-customer-approval .cca-audit-item:not(:last-child)::before {
        content: '';
        position: absolute;
        left: 0.3125rem;
        top: 1.125rem;
        bottom: -0.875rem;
        width: 1px;
        background: rgb(229 231 235);
    }

    .fi-page-credit-customer-approval .cca-audit-dot {
        width: 0.625rem;
        height: 0.625rem;
        border-radius: 9999px;
        border: 2px solid rgb(209 213 219);
        background: white;
        margin-top: 0.3125rem;
        flex-shrink: 0;
    }

    .fi-page-credit-customer-approval .cca-audit-item-active .cca-audit-dot {
        background: rgb(17 24 39);
        border-color: rgb(17 24 39);
    }

    .fi-page-credit-customer-approval .cca-audit-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-page-credit-customer-approval .cca-audit-meta {
        font-size: 0.75rem;
        color: rgb(107 114 128);
        margin-top: 0.125rem;
    }

    .fi-page-credit-customer-approval .cca-actions {
        padding: 0.875rem 1.125rem 1.125rem;
        border-top: 1px solid rgb(229 231 235);
        background: white;
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        flex-shrink: 0;
    }

    .fi-page-credit-customer-approval .cca-secondary-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.625rem;
    }

    .fi-page-credit-customer-approval .cca-inline-form {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-page-credit-customer-approval .cca-inline-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .fi-page-credit-customer-approval .cca-form-input {
        width: 100%;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        resize: vertical;
    }

    .fi-page-credit-customer-approval .cca-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        border-radius: 0.5rem;
        padding: 0.5625rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .fi-page-credit-customer-approval .cca-btn svg {
        width: 1rem;
        height: 1rem;
    }

    .fi-page-credit-customer-approval .cca-btn-sm {
        padding: 0.375rem 0.625rem;
        font-size: 0.75rem;
    }

    .fi-page-credit-customer-approval .cca-btn-icon {
        padding: 0.4375rem 0.75rem;
    }

    .fi-page-credit-customer-approval .cca-btn-primary {
        width: 100%;
        background: rgb(15 23 42);
        color: white;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        font-size: 0.75rem;
        padding: 0.6875rem 0.875rem;
    }

    .fi-page-credit-customer-approval .cca-btn-outline {
        background: white;
        border-color: rgb(209 213 219);
        color: rgb(55 65 81);
    }

    .fi-page-credit-customer-approval .cca-btn-danger-outline {
        background: white;
        border-color: rgb(252 165 165);
        color: rgb(185 28 28);
    }

    .fi-page-credit-customer-approval .cca-btn-half {
        width: 100%;
    }

    @media (max-width: 1280px) {
        .fi-page-credit-customer-approval .cca-layout {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .fi-page-credit-customer-approval .cca-filter-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fi-page-credit-customer-approval .cca-summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

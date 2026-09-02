<style>
    .fi-page-portal-enquiries .fi-header-heading {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-page-portal-enquiries .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        max-width: 48rem;
    }

    .fi-page-portal-enquiries .cor-toolbar {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        padding: 0.875rem 1rem;
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
    }

    .fi-page-portal-enquiries .cor-toolbar-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.875rem 1rem;
        align-items: flex-end;
        flex: 1;
    }

    .fi-page-portal-enquiries .cor-toolbar-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-page-portal-enquiries .cor-toolbar-search {
        min-width: 14rem;
        flex: 1;
    }

    .fi-page-portal-enquiries .cor-toolbar-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-page-portal-enquiries .cor-toolbar-input {
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.4375rem 0.625rem;
        font-size: 0.8125rem;
        color: rgb(17 24 39);
        background: white;
        min-width: 0;
    }

    .fi-page-portal-enquiries .cor-date-range {
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .fi-page-portal-enquiries .cor-date-sep {
        color: rgb(156 163 175);
        font-size: 0.875rem;
    }

    .fi-page-portal-enquiries .cor-layout {
        display: grid;
        grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
        gap: 1rem;
        align-items: start;
    }

    .fi-page-portal-enquiries .cor-queue-panel,
    .fi-page-portal-enquiries .cor-detail-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        min-height: 36rem;
    }

    .fi-page-portal-enquiries .cor-queue-panel {
        padding: 1rem;
    }

    .fi-page-portal-enquiries .cor-detail-panel {
        padding: 1.125rem;
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }

    .fi-page-portal-enquiries .cor-panel-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-page-portal-enquiries .cor-panel-sub {
        margin: 0.25rem 0 0;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-page-portal-enquiries .cor-queue-head {
        margin-bottom: 0.875rem;
    }

    .fi-page-portal-enquiries .cor-table-wrap {
        overflow-x: auto;
    }

    .fi-page-portal-enquiries .cor-table,
    .fi-page-portal-enquiries .cor-items-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .fi-page-portal-enquiries .cor-table th,
    .fi-page-portal-enquiries .cor-items-table th {
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

    .fi-page-portal-enquiries .cor-table td,
    .fi-page-portal-enquiries .cor-items-table td {
        padding: 0.75rem 0.5rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(17 24 39);
        vertical-align: top;
    }

    .fi-page-portal-enquiries .cor-row {
        cursor: pointer;
    }

    .fi-page-portal-enquiries .cor-row:hover {
        background: rgb(249 250 251);
    }

    .fi-page-portal-enquiries .cor-row-selected {
        background: rgb(239 246 255);
    }

    .fi-page-portal-enquiries .cor-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .fi-page-portal-enquiries .cor-destination {
        max-width: 10rem;
        color: rgb(75 85 99);
    }

    .fi-page-portal-enquiries .cor-status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.1875rem 0.5rem;
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .fi-page-portal-enquiries .cor-status-gray { background: rgb(243 244 246); color: rgb(75 85 99); }
    .fi-page-portal-enquiries .cor-status-blue { background: rgb(219 234 254); color: rgb(29 78 216); }
    .fi-page-portal-enquiries .cor-status-approved { background: rgb(224 242 254); color: rgb(3 105 161); }
    .fi-page-portal-enquiries .cor-status-danger { background: rgb(254 226 226); color: rgb(185 28 28); }

    .fi-page-portal-enquiries .cor-empty,
    .fi-page-portal-enquiries .cor-detail-empty {
        padding: 2rem 1rem;
        text-align: center;
        color: rgb(107 114 128);
    }

    .fi-page-portal-enquiries .cor-detail-empty p {
        margin: 0.5rem 0 0;
        font-size: 0.875rem;
    }

    .fi-page-portal-enquiries .cor-order-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .fi-page-portal-enquiries .cor-order-head-top {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        flex-wrap: wrap;
    }

    .fi-page-portal-enquiries .cor-order-id {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-page-portal-enquiries .cor-order-meta {
        margin: 0.375rem 0 0;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-page-portal-enquiries .cor-branch-tag {
        font-weight: 600;
        color: rgb(55 65 81);
        text-transform: uppercase;
        font-size: 0.75rem;
    }

    .fi-page-portal-enquiries .cor-external-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        color: rgb(75 85 99);
    }

    .fi-page-portal-enquiries .cor-external-link svg {
        width: 1rem;
        height: 1rem;
    }

    .fi-page-portal-enquiries .cor-pickup-delivery {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .fi-page-portal-enquiries .cor-info-card,
    .fi-page-portal-enquiries .cor-items-card,
    .fi-page-portal-enquiries .cor-mini-card {
        border: 1px solid rgb(243 244 246);
        border-radius: 0.625rem;
        padding: 0.875rem;
        background: rgb(249 250 251);
    }

    .fi-page-portal-enquiries .cor-section-title {
        margin: 0 0 0.625rem;
        font-size: 0.8125rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-page-portal-enquiries .cor-info-block + .cor-info-block {
        margin-top: 0.625rem;
    }

    .fi-page-portal-enquiries .cor-info-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        margin-bottom: 0.125rem;
    }

    .fi-page-portal-enquiries .cor-info-value {
        font-size: 0.8125rem;
        color: rgb(17 24 39);
        white-space: pre-wrap;
    }

    .fi-page-portal-enquiries .cor-link {
        display: inline-block;
        margin-top: 0.5rem;
        font-size: 0.75rem;
        color: rgb(29 78 216);
        text-decoration: underline;
    }

    .fi-page-portal-enquiries .cor-item-name {
        font-weight: 600;
    }

    .fi-page-portal-enquiries .cor-item-packaging {
        font-size: 0.6875rem;
        color: rgb(107 114 128);
        margin-top: 0.125rem;
    }

    .fi-page-portal-enquiries .cor-special-request {
        color: rgb(185 28 28);
        font-size: 0.75rem;
    }

    .fi-page-portal-enquiries .cor-items-total {
        text-align: right;
        font-size: 0.8125rem;
        font-weight: 700;
        color: rgb(55 65 81);
        padding-top: 0.75rem !important;
        border-bottom: none !important;
    }

    .fi-page-portal-enquiries .cor-bottom-cards {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .fi-page-portal-enquiries .cor-mini-card-wide {
        grid-column: 1 / -1;
    }

    .fi-page-portal-enquiries .cor-pricing-ref {
        font-size: 0.75rem;
        color: rgb(107 114 128);
        margin-bottom: 0.25rem;
    }

    .fi-page-portal-enquiries .cor-pricing-amount {
        font-size: 1.25rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 0.5rem;
    }

    .fi-page-portal-enquiries .cor-verify-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.125rem 0.5rem;
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-page-portal-enquiries .cor-verify-badge-success {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-page-portal-enquiries .cor-verify-badge-pending {
        background: rgb(254 243 199);
        color: rgb(146 64 14);
    }

    .fi-page-portal-enquiries .cor-mini-note {
        margin: 0.5rem 0 0;
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .fi-page-portal-enquiries .cor-payment-empty,
    .fi-page-portal-enquiries .cor-payment-thumb {
        border: 1px dashed rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 1rem;
        text-align: center;
        font-size: 0.75rem;
        color: rgb(107 114 128);
        background: white;
        margin-bottom: 0.625rem;
    }

    .fi-page-portal-enquiries .cor-payment-meta {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        font-size: 0.8125rem;
        color: rgb(17 24 39);
    }

    .fi-page-portal-enquiries .cor-payment-meta span {
        color: rgb(107 114 128);
        font-size: 0.6875rem;
        font-weight: 700;
        text-transform: uppercase;
        margin-right: 0.375rem;
    }

    .fi-page-portal-enquiries .cor-trace-flow {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem;
        margin-bottom: 0.75rem;
    }

    .fi-page-portal-enquiries .cor-trace-step {
        border: 1px solid rgb(229 231 235);
        border-radius: 9999px;
        padding: 0.25rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 600;
        color: rgb(107 114 128);
        background: white;
    }

    .fi-page-portal-enquiries .cor-trace-step-completed {
        background: rgb(220 252 231);
        border-color: rgb(187 247 208);
        color: rgb(21 128 61);
    }

    .fi-page-portal-enquiries .cor-trace-step-active {
        background: rgb(219 234 254);
        border-color: rgb(191 219 254);
        color: rgb(29 78 216);
    }

    .fi-page-portal-enquiries .cor-notification-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .fi-page-portal-enquiries .cor-notification-list li {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.8125rem;
        color: rgb(55 65 81);
    }

    .fi-page-portal-enquiries .cor-notification-list strong {
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-page-portal-enquiries .cor-rejection-banner {
        border: 1px solid rgb(252 165 165);
        background: rgb(254 242 242);
        color: rgb(185 28 28);
        border-radius: 0.5rem;
        padding: 0.625rem 0.75rem;
        font-size: 0.8125rem;
    }

    .fi-page-portal-enquiries .cor-footer-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.625rem;
        margin-top: auto;
        padding-top: 0.875rem;
        border-top: 1px solid rgb(229 231 235);
    }

    .fi-page-portal-enquiries .cor-reject-form {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-page-portal-enquiries .cor-reject-form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
    }

    .fi-page-portal-enquiries .cor-reject-input {
        width: 100%;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.5rem 0.75rem;
        font-size: 0.8125rem;
        resize: vertical;
    }

    .fi-page-portal-enquiries .cor-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .fi-page-portal-enquiries .cor-btn-outline {
        background: white;
        border-color: rgb(209 213 219);
        color: rgb(55 65 81);
    }

    .fi-page-portal-enquiries .cor-btn-danger-outline {
        background: white;
        border-color: rgb(252 165 165);
        color: rgb(185 28 28);
    }

    .fi-page-portal-enquiries .cor-btn-danger-solid {
        background: rgb(254 226 226);
        border-color: rgb(252 165 165);
        color: rgb(185 28 28);
    }

    .fi-page-portal-enquiries .cor-btn-secondary-solid {
        background: rgb(55 65 81);
        color: white;
    }

    .fi-page-portal-enquiries .cor-btn-success {
        background: rgb(22 163 74);
        color: white;
    }

    @media (max-width: 1280px) {
        .fi-page-portal-enquiries .cor-layout {
            grid-template-columns: 1fr;
        }

        .fi-page-portal-enquiries .cor-pickup-delivery,
        .fi-page-portal-enquiries .cor-bottom-cards {
            grid-template-columns: 1fr;
        }
    }
</style>

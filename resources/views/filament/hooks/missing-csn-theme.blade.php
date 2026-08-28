<style>
    .fi-resource-missing-csn-logs.fi-resource-list-records-page .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        margin-top: 0.25rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-page-intro {
        margin-bottom: 1rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-page-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-badge-date {
        background: rgb(239 246 255);
        color: rgb(29 78 216);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-card {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1rem 1.125rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-card-warning {
        border-left: 4px solid rgb(245 158 11);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-card-danger {
        border-left: 4px solid rgb(239 68 68);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-icon-wrap {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 0.625rem;
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-icon-warning {
        background: rgb(255 251 235);
        color: rgb(217 119 6);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-icon-danger {
        background: rgb(254 242 242);
        color: rgb(220 38 38);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-icon {
        width: 1.375rem;
        height: 1.375rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-content {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-label {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-value {
        font-size: 1.625rem;
        font-weight: 700;
        line-height: 1;
        color: rgb(17 24 39);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-value-warning {
        color: rgb(217 119 6);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stat-value-danger {
        color: rgb(220 38 38);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-card,
    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-card,
    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-list-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        margin-bottom: 1rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-card {
        padding: 1rem 1.25rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.875rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-icon {
        width: 1.125rem;
        height: 1.125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-title {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-active {
        margin-left: auto;
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
        background: rgb(220 252 231);
        color: rgb(21 128 61);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-body {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-param {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-param-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-param-value {
        font-size: 0.875rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-flow {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-step {
        font-size: 0.8125rem;
        color: rgb(75 85 99);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-arrow {
        color: rgb(156 163 175);
        font-size: 0.875rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-rule-note {
        margin: 0;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        line-height: 1.5;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-card {
        padding: 1rem 1.25rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-head {
        margin-bottom: 0.875rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-title-wrap {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-icon {
        width: 1rem;
        height: 1rem;
        color: rgb(107 114 128);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-title {
        margin: 0;
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-form {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-grid-top {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.875rem 1rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-grid-bottom {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) auto;
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-actions {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding-bottom: 0.125rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.375rem;
        padding: 0.4375rem 1.125rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-btn-primary {
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-list-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin: 0;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-record-count {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(107 114 128);
        background: rgb(243 244 246);
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-table-wrap {
        overflow-x: auto;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-table th {
        background: rgb(249 250 251);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        text-align: left;
        padding: 0.75rem 1rem;
        white-space: nowrap;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-table td {
        padding: 0.875rem 1rem;
        border-top: 1px solid rgb(243 244 246);
        color: rgb(55 65 81);
        vertical-align: middle;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-cell-strong {
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-cell-danger {
        color: rgb(220 38 38);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-row-alert-icon {
        width: 1rem;
        height: 1rem;
        display: inline-block;
        vertical-align: -0.125rem;
        margin-right: 0.25rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-csn-link {
        color: inherit;
        text-decoration: none;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-csn-link:hover {
        text-decoration: underline;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-days-overdue {
        color: rgb(220 38 38);
        font-weight: 700;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-status-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-status-tag-warning {
        background: rgb(254 243 199);
        color: rgb(180 83 9);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-status-tag-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-empty {
        text-align: center;
        padding: 2rem 1rem;
        color: rgb(107 114 128);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.875rem 1.25rem;
        border-top: 1px solid rgb(243 244 246);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-pagination-meta {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-pagination-controls {
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-page-btn {
        min-width: 2rem;
        min-height: 2rem;
        padding: 0.25rem 0.625rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.375rem;
        background: white;
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(55 65 81);
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-page-btn-active {
        background: rgb(15 23 42);
        border-color: rgb(15 23 42);
        color: white;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-page-btn:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-footer-link {
        padding-top: 0.25rem;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-back-link {
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(55 65 81);
        text-decoration: none;
    }

    .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-back-link:hover {
        color: rgb(17 24 39);
        text-decoration: underline;
    }

    @media (max-width: 1024px) {
        .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stats-grid,
        .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-grid-top,
        .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-grid-bottom {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-stats-grid,
        .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-grid-top,
        .fi-resource-missing-csn-logs.fi-resource-list-records-page .mc-filter-grid-bottom {
            grid-template-columns: 1fr;
        }
    }
</style>

<style>
    .fi-page-delivery-monitoring .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-page-delivery-monitoring .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        margin-top: 0.25rem;
    }

    .fi-page-delivery-monitoring .dm-page-intro {
        margin-bottom: 1rem;
    }

    .fi-page-delivery-monitoring .dm-page-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .fi-page-delivery-monitoring .dm-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-page-delivery-monitoring .dm-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-page-delivery-monitoring .dm-badge-date {
        background: rgb(239 246 255);
        color: rgb(29 78 216);
    }

    .fi-page-delivery-monitoring .dm-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
        margin-bottom: 1rem;
    }

    .fi-page-delivery-monitoring .dm-stat-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 0.875rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-page-delivery-monitoring .dm-stat-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-page-delivery-monitoring .dm-stat-value {
        font-size: 1.375rem;
        font-weight: 700;
        color: rgb(17 24 39);
        line-height: 1.1;
    }

    .fi-page-delivery-monitoring .dm-stat-value-info { color: rgb(29 78 216); }
    .fi-page-delivery-monitoring .dm-stat-value-warning { color: rgb(217 119 6); }
    .fi-page-delivery-monitoring .dm-stat-value-success { color: rgb(21 128 61); }
    .fi-page-delivery-monitoring .dm-stat-value-danger { color: rgb(220 38 38); }
    .fi-page-delivery-monitoring .dm-stat-value-purple { color: rgb(126 34 206); }
    .fi-page-delivery-monitoring .dm-stat-value-muted { color: rgb(107 114 128); }

    .fi-page-delivery-monitoring .dm-filter-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .fi-page-delivery-monitoring .dm-filter-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 0.875rem;
    }

    .fi-page-delivery-monitoring .dm-filter-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-page-delivery-monitoring .dm-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-page-delivery-monitoring .dm-filter-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-page-delivery-monitoring .dm-filter-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-page-delivery-monitoring .dm-filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.625rem;
        grid-column: 1 / -1;
    }

    .fi-page-delivery-monitoring .dm-btn {
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

    .fi-page-delivery-monitoring .dm-btn-primary {
        background: rgb(15 23 42);
        color: white;
    }

    .fi-page-delivery-monitoring .dm-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-page-delivery-monitoring .dm-btn-danger {
        background: rgb(220 38 38);
        color: white;
        border-color: rgb(220 38 38);
        white-space: nowrap;
    }

    .fi-page-delivery-monitoring .dm-legend {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        flex-wrap: wrap;
        margin-bottom: 0.75rem;
    }

    .fi-page-delivery-monitoring .dm-legend-title {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(75 85 99);
    }

    .fi-page-delivery-monitoring .dm-legend-items {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .fi-page-delivery-monitoring .dm-legend-badge,
    .fi-page-delivery-monitoring .dm-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .fi-page-delivery-monitoring .dm-legend-badge-gray,
    .fi-page-delivery-monitoring .dm-status-badge-gray {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-page-delivery-monitoring .dm-legend-badge-warning,
    .fi-page-delivery-monitoring .dm-status-badge-warning {
        background: rgb(254 243 199);
        color: rgb(180 83 9);
    }

    .fi-page-delivery-monitoring .dm-legend-badge-success,
    .fi-page-delivery-monitoring .dm-status-badge-success {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-page-delivery-monitoring .dm-legend-badge-danger,
    .fi-page-delivery-monitoring .dm-status-badge-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-page-delivery-monitoring .dm-legend-badge-info,
    .fi-page-delivery-monitoring .dm-status-badge-info {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .fi-page-delivery-monitoring .dm-legend-badge-primary,
    .fi-page-delivery-monitoring .dm-status-badge-primary {
        background: rgb(243 232 255);
        color: rgb(126 34 206);
    }

    .fi-page-delivery-monitoring .dm-table-card,
    .fi-page-delivery-monitoring .dm-alert-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        margin-bottom: 1rem;
        overflow: hidden;
    }

    .fi-page-delivery-monitoring .dm-alert-card {
        background: rgb(254 242 242);
        border-color: rgb(254 202 202);
        padding: 1rem 1.25rem 1.25rem;
    }

    .fi-page-delivery-monitoring .dm-table-wrap {
        overflow-x: auto;
    }

    .fi-page-delivery-monitoring .dm-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-page-delivery-monitoring .dm-table th {
        background: rgb(249 250 251);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        text-align: left;
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(229 231 235);
        white-space: nowrap;
    }

    .fi-page-delivery-monitoring .dm-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(31 41 55);
        vertical-align: middle;
    }

    .fi-page-delivery-monitoring .dm-table tr:last-child td {
        border-bottom: none;
    }

    .fi-page-delivery-monitoring .dm-cell-strong {
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-page-delivery-monitoring .dm-empty {
        text-align: center;
        color: rgb(107 114 128);
        padding: 2rem 1rem;
    }

    .fi-page-delivery-monitoring .dm-alert-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 0.75rem;
    }

    .fi-page-delivery-monitoring .dm-alert-title-wrap {
        display: flex;
        align-items: flex-start;
        gap: 0.625rem;
    }

    .fi-page-delivery-monitoring .dm-alert-icon {
        width: 1.25rem;
        height: 1.25rem;
        color: rgb(220 38 38);
        flex-shrink: 0;
        margin-top: 0.125rem;
    }

    .fi-page-delivery-monitoring .dm-alert-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(185 28 28);
    }

    .fi-page-delivery-monitoring .dm-alert-meta {
        font-size: 0.8125rem;
        color: rgb(127 29 29);
        margin-top: 0.25rem;
    }

    .fi-page-delivery-monitoring .dm-alert-rule {
        font-size: 0.8125rem;
        color: rgb(127 29 29);
        margin: 0 0 1rem;
    }

    .fi-page-delivery-monitoring .dm-alert-table {
        background: white;
        border: 1px solid rgb(254 202 202);
        border-radius: 0.5rem;
        overflow: hidden;
    }

    @media (max-width: 768px) {
        .fi-page-delivery-monitoring .dm-stats-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fi-page-delivery-monitoring .dm-filter-grid {
            grid-template-columns: 1fr;
        }

        .fi-page-delivery-monitoring .dm-alert-header {
            flex-direction: column;
        }
    }
</style>

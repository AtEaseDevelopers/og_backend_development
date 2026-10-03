<style>
    /* ------------------------------------------------------------------
     | Orders & CSN combined workspace (app/Filament/Pages/OrderOperations)
     | Builds on the .cor-* rules from portal-enquiries-theme.
     * ------------------------------------------------------------------ */

    .fi-page-order-operations .fi-header-heading {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-page-order-operations .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        max-width: 52rem;
    }

    /* Tabs -------------------------------------------------------------- */
    .fi-page-order-operations .ops-tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
        padding: 0.375rem;
        background: rgb(241 245 249);
        border: 1px solid rgb(226 232 240);
        border-radius: 0.875rem;
    }

    .fi-page-order-operations .ops-tab {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 0.625rem;
        border: 1px solid transparent;
        background: transparent;
        color: rgb(71 85 105);
        text-align: left;
        text-decoration: none;
        cursor: pointer;
        transition: background 120ms ease, color 120ms ease, box-shadow 120ms ease;
    }

    .fi-page-order-operations .ops-tab:hover {
        background: rgb(248 250 252);
        color: rgb(15 23 42);
    }

    .fi-page-order-operations .ops-tab-active,
    .fi-page-order-operations .ops-tab-active:hover {
        background: rgb(15 23 42);
        color: white;
        box-shadow: 0 1px 2px rgb(15 23 42 / 0.25);
    }

    .fi-page-order-operations .ops-tab-icon {
        display: inline-flex;
        width: 2.25rem;
        height: 2.25rem;
        align-items: center;
        justify-content: center;
        border-radius: 0.5rem;
        background: rgb(226 232 240);
        color: rgb(51 65 85);
        flex: none;
    }

    .fi-page-order-operations .ops-tab-icon svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .fi-page-order-operations .ops-tab-active .ops-tab-icon {
        background: rgb(255 255 255 / 0.12);
        color: white;
    }

    .fi-page-order-operations .ops-tab-text {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        min-width: 0;
        flex: 1;
    }

    .fi-page-order-operations .ops-tab-label {
        font-size: 0.9375rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .fi-page-order-operations .ops-tab-sub {
        font-size: 0.75rem;
        opacity: 0.75;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fi-page-order-operations .ops-tab-count {
        flex: none;
        min-width: 2rem;
        padding: 0.125rem 0.5rem;
        border-radius: 9999px;
        background: white;
        color: rgb(15 23 42);
        font-size: 0.75rem;
        font-weight: 700;
        text-align: center;
        border: 1px solid rgb(226 232 240);
    }

    .fi-page-order-operations .ops-tab-active .ops-tab-count {
        background: rgb(255 255 255 / 0.14);
        color: white;
        border-color: transparent;
    }

    /* Section -------------------------------------------------------- */
    .fi-page-order-operations .ops-section {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .fi-page-order-operations .ops-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }

    .fi-page-order-operations .ops-section-title {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 700;
        letter-spacing: -0.01em;
        color: rgb(17 24 39);
    }

    .fi-page-order-operations .ops-section-sub {
        margin: 0.25rem 0 0;
        font-size: 0.875rem;
        color: rgb(107 114 128);
    }

    .fi-page-order-operations .ops-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        border-radius: 0.5rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        text-decoration: none;
        white-space: nowrap;
        cursor: pointer;
    }

    .fi-page-order-operations .ops-btn-primary {
        background: rgb(15 23 42);
        color: white;
        border: 1px solid rgb(15 23 42);
    }

    .fi-page-order-operations .ops-btn-primary:hover {
        background: rgb(30 41 59);
    }

    .fi-page-order-operations .ops-btn-sm {
        padding: 0.3125rem 0.625rem;
        font-size: 0.75rem;
    }

    /* Stat cards ------------------------------------------------------- */
    .fi-page-order-operations .ops-cards {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.75rem;
    }

    .fi-page-order-operations .ops-card {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        padding: 0.875rem 1rem;
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        text-align: left;
        cursor: pointer;
        transition: border-color 120ms ease, box-shadow 120ms ease;
    }

    .fi-page-order-operations .ops-card:hover {
        border-color: rgb(148 163 184);
        box-shadow: 0 1px 3px rgb(0 0 0 / 0.06);
    }

    .fi-page-order-operations .ops-card-active,
    .fi-page-order-operations .ops-card-active:hover {
        background: rgb(15 23 42);
        border-color: rgb(15 23 42);
        color: white;
    }

    .fi-page-order-operations .ops-card-label {
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .fi-page-order-operations .ops-card-value {
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.1;
        color: rgb(29 78 216);
    }

    .fi-page-order-operations .ops-card:nth-child(2) .ops-card-value { color: rgb(180 83 9); }
    .fi-page-order-operations .ops-card:nth-child(3) .ops-card-value { color: rgb(126 34 206); }
    .fi-page-order-operations .ops-card:nth-child(4) .ops-card-value { color: rgb(21 128 61); }

    .fi-page-order-operations .ops-card-active .ops-card-value,
    .fi-page-order-operations .ops-card-active .ops-card-hint {
        color: white;
    }

    .fi-page-order-operations .ops-card-hint {
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    /* Legend chips ----------------------------------------------------- */
    .fi-page-order-operations .ops-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem;
    }

    .fi-page-order-operations .ops-legend-item {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
    }

    .fi-page-order-operations .ops-legend-item::before {
        content: '';
        width: 0.375rem;
        height: 0.375rem;
        border-radius: 9999px;
        background: currentColor;
    }

    .fi-page-order-operations .ops-legend-gray { background: rgb(243 244 246); color: rgb(75 85 99); }
    .fi-page-order-operations .ops-legend-blue { background: rgb(219 234 254); color: rgb(29 78 216); }
    .fi-page-order-operations .ops-legend-approved { background: rgb(220 252 231); color: rgb(21 128 61); }
    .fi-page-order-operations .ops-legend-danger { background: rgb(254 226 226); color: rgb(185 28 28); }

    /* Toolbar tweaks --------------------------------------------------- */
    .fi-page-order-operations .ops-toolbar {
        margin-bottom: 0;
    }

    .fi-page-order-operations .cor-toolbar-actions {
        display: flex;
        gap: 0.5rem;
    }

    /* Panels & table --------------------------------------------------- */
    .fi-page-order-operations .ops-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
        overflow: hidden;
    }

    .fi-page-order-operations .ops-table th {
        padding: 0.75rem 1rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        background: rgb(249 250 251);
        border-bottom: 1px solid rgb(229 231 235);
        text-align: left;
        white-space: nowrap;
    }

    .fi-page-order-operations .ops-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        vertical-align: top;
    }

    .fi-page-order-operations .ops-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .fi-page-order-operations .ops-th-right,
    .fi-page-order-operations .ops-td-right {
        text-align: right;
    }

    .fi-page-order-operations .ops-row td:first-child {
        border-left: 3px solid transparent;
    }

    .fi-page-order-operations .ops-row-gray td:first-child { border-left-color: rgb(156 163 175); }
    .fi-page-order-operations .ops-row-blue td:first-child { border-left-color: rgb(59 130 246); }
    .fi-page-order-operations .ops-row-approved td:first-child { border-left-color: rgb(34 197 94); }
    .fi-page-order-operations .ops-row-danger td:first-child { border-left-color: rgb(239 68 68); }

    .fi-page-order-operations .ops-primary {
        font-size: 0.8125rem;
        font-weight: 700;
        color: rgb(29 78 216);
    }

    .fi-page-order-operations .ops-secondary {
        font-size: 0.8125rem;
        color: rgb(31 41 55);
        margin-top: 0.125rem;
    }

    .fi-page-order-operations .ops-tertiary {
        font-size: 0.6875rem;
        color: rgb(107 114 128);
        margin-top: 0.25rem;
    }

    .fi-page-order-operations .ops-route {
        margin-top: 0;
    }

    .fi-page-order-operations .ops-amount {
        font-size: 0.8125rem;
        font-weight: 700;
        color: rgb(17 24 39);
        white-space: nowrap;
    }

    .fi-page-order-operations .ops-amount-muted {
        color: rgb(107 114 128);
        font-weight: 500;
    }

    .fi-page-order-operations .ops-next {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(29 78 216);
        text-decoration: none;
        white-space: nowrap;
    }

    .fi-page-order-operations a.ops-next:hover {
        text-decoration: underline;
    }

    .fi-page-order-operations .ops-panel-foot {
        display: flex;
        justify-content: space-between;
        padding: 0.625rem 1rem;
        font-size: 0.75rem;
        color: rgb(107 114 128);
        border-top: 1px solid rgb(243 244 246);
        background: rgb(249 250 251);
    }

    /* Detail panel below the list ------------------------------------- */
    .fi-page-order-operations .ops-detail {
        min-height: 0;
    }

    .fi-page-order-operations .ops-detail-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px dashed rgb(229 231 235);
    }

    .fi-page-order-operations .ops-detail-crumb {
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    /* CSN sub-tabs ----------------------------------------------------- */
    .fi-page-order-operations .ops-subtabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem;
    }

    .fi-page-order-operations .ops-subtab {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        background: white;
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(71 85 105);
        cursor: pointer;
    }

    .fi-page-order-operations .ops-subtab:hover {
        border-color: rgb(148 163 184);
        color: rgb(15 23 42);
    }

    .fi-page-order-operations .ops-subtab-active,
    .fi-page-order-operations .ops-subtab-active:hover {
        background: rgb(241 245 249);
        border-color: rgb(15 23 42);
        color: rgb(15 23 42);
    }

    .fi-page-order-operations .ops-subtab-count {
        padding: 0 0.375rem;
        border-radius: 9999px;
        background: rgb(241 245 249);
        font-size: 0.6875rem;
        color: rgb(71 85 105);
    }

    .fi-page-order-operations .ops-subtab-active .ops-subtab-count {
        background: rgb(15 23 42);
        color: white;
    }

    /* Embedded Filament table (CSN tab) ------------------------------- */
    .fi-page-order-operations .ops-table-panel {
        padding: 0;
        border: 0;
        box-shadow: none;
        background: transparent;
        overflow: visible;
    }

    /* Flow footnote ---------------------------------------------------- */
    .fi-page-order-operations .ops-flow {
        margin: 0;
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    @media (max-width: 1024px) {
        .fi-page-order-operations .ops-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .fi-page-order-operations .ops-tabs,
        .fi-page-order-operations .ops-cards {
            grid-template-columns: 1fr;
        }

        .fi-page-order-operations .ops-section-head {
            flex-direction: column;
        }
    }
</style>

<style>
    /* Quotation list — page header */
    .fi-resource-quotations.fi-resource-list-records-page .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-header-subheading {
        color: rgb(107 114 128);
        font-size: 0.9375rem;
        margin-top: 0.25rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ac-btn-action[data-action="create"] {
        font-weight: 600;
        background: rgb(15 23 42) !important;
        color: white !important;
        border: none !important;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ac-btn-action[data-action="create"]:hover {
        background: rgb(30 41 59) !important;
    }

    /* Stats overview cards */
    .fi-resource-quotations.fi-resource-list-records-page .fi-wi-stats-overview {
        gap: 0;
        margin-bottom: 0.25rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-wi-stats-overview-stats-ctn {
        gap: 1rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-wi-stats-overview-stat {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: white;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1.125rem 1.25rem !important;
        ring: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-wi-stats-overview-stat-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: rgb(107 114 128);
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-wi-stats-overview-stat-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-wi-stats-overview-stat-description {
        font-size: 0.8125rem;
        font-weight: 500;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-wi-stats-overview-stat-description-icon {
        width: 1rem;
        height: 1rem;
    }

    /* Quotation list — search & filter + table container */
    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-ctn {
        background: white !important;
        border: 1px solid rgb(229 231 235) !important;
        border-radius: 0.75rem !important;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04) !important;
        overflow: hidden !important;
        margin-top: 1rem;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-ctn {
        background: transparent;
        border: none;
        box-shadow: none;
        margin-bottom: 0;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-ctn.divide-y > :not([hidden]) ~ :not([hidden]) {
        border-top-width: 0;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-toolbar {
        display: flex;
        align-items: center;
        padding: 0.875rem 1rem !important;
        gap: 1rem;
        min-height: 3.25rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-toolbar > .flex.shrink-0:empty,
    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-toolbar > .flex.shrink-0:not(:has(*:not([x-cloak]))) {
        display: none;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto {
        margin-left: 0 !important;
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-search-field {
        flex: 0 1 20rem;
        width: 100%;
        max-width: 20rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-search-field .fi-input-wrp {
        border-radius: 0.375rem;
        background: rgb(249 250 251);
        box-shadow: none;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-search-field input {
        font-size: 0.875rem;
        background: transparent;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-search-field input::placeholder {
        color: rgb(156 163 175);
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-filters-dropdown,
    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-column-toggle-dropdown {
        flex-shrink: 0;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto > div:last-child {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: auto;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-filters-dropdown .fi-icon-btn,
    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-column-toggle-dropdown .fi-icon-btn {
        color: rgb(107 114 128);
        position: relative;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-filters-dropdown .fi-icon-btn:hover,
    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-column-toggle-dropdown .fi-icon-btn:hover {
        color: rgb(55 65 81);
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-filters-dropdown .fi-badge {
        position: absolute;
        top: -0.25rem;
        right: -0.25rem;
        min-width: 1.125rem;
        height: 1.125rem;
        padding: 0 0.25rem;
        font-size: 0.6875rem;
        font-weight: 600;
        line-height: 1.125rem;
        border-radius: 9999px;
        background: rgb(243 244 246);
        color: rgb(75 85 99);
        border: 1px solid rgb(229 231 235);
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-filter-indicators {
        background: rgb(249 250 251);
        border-top: 1px solid rgb(243 244 246);
        padding: 0.625rem 1rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-content {
        background: white;
        border-top: 1px solid rgb(243 244 246);
        overflow: hidden;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-cell {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-cell {
        font-size: 0.875rem;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-ta-actions .fi-link {
        font-size: 0.8125rem;
        font-weight: 500;
    }

    .fi-resource-quotations.fi-resource-list-records-page .fi-page-main > .flex.flex-col.gap-y-6 {
        gap: 1rem;
    }

    @media (max-width: 767px) {
        .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto {
            flex-direction: column;
            align-items: stretch;
        }

        .fi-resource-quotations.fi-resource-list-records-page .fi-ta-search-field {
            max-width: none;
        }

        .fi-resource-quotations.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto > div:last-child {
            justify-content: flex-end;
        }
    }

    /* Quotation list — fallback for non-list pages */
    .fi-resource-quotations:not(.fi-resource-list-records-page) .fi-wi-stats-overview-stat {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: white;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
    }

    .fi-resource-quotations:not(.fi-resource-list-records-page) .fi-ta-ctn {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: white;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        overflow: hidden;
    }

    /* View quotation layout */
    .fi-resource-quotations.fi-resource-view-record-page .quotation-view {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        width: 100%;
        font-size: 0.875rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-card {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: white;
        padding: 1rem 1.25rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-card {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-summary-card {
        background: rgb(249 250 251);
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-summary-card {
        background: rgb(31 41 55);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-card-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 1rem;
        padding-bottom: 0.625rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-card-title {
        color: rgb(243 244 246);
        border-bottom-color: rgb(55 65 81);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-card-title-icon {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-card-title-icon svg {
        width: 1rem;
        height: 1rem;
        color: rgb(107 114 128);
        flex-shrink: 0;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        line-height: 1.3;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-value {
        margin-top: 0.375rem;
        color: rgb(17 24 39);
        font-size: 0.875rem;
        line-height: 1.45;
        min-height: 1.25rem;
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-value {
        color: rgb(243 244 246);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-value-strong {
        font-weight: 700;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-value-multiline {
        white-space: pre-line;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-field {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-field-stack > .qv-field {
        padding-bottom: 0.875rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-field-stack > .qv-field:not(:last-child) {
        border-bottom: 1px solid rgb(243 244 246);
        margin-bottom: 0.125rem;
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-field-stack > .qv-field:not(:last-child) {
        border-bottom-color: rgb(55 65 81);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-2,
    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-3,
    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-6 {
        display: grid;
        gap: 1rem;
        width: 100%;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-6 {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }

    @media (max-width: 1279px) {
        .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-6 {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-2,
        .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-3,
        .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-grid-6 {
            grid-template-columns: 1fr;
        }
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-split {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1.25rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-split > .qv-field-stack:first-child {
        padding-right: 1rem;
        border-right: 1px solid rgb(243 244 246);
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-split > .qv-field-stack:first-child {
        border-right-color: rgb(55 65 81);
    }

    @media (max-width: 767px) {
        .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-split {
            grid-template-columns: 1fr;
        }

        .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-split > .qv-field-stack:first-child {
            padding-right: 0;
            border-right: none;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgb(243 244 246);
        }
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-status-dot {
        width: 0.625rem;
        height: 0.625rem;
        border-radius: 9999px;
        background: rgb(156 163 175);
        flex-shrink: 0;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-status-dot-active {
        background: rgb(16 185 129);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-link {
        color: rgb(37 99 235);
        font-weight: 600;
        text-decoration: none;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-link:hover {
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-section {
        margin-bottom: 1.25rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-section-last {
        margin-bottom: 0;
        margin-top: 1.25rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-section > .qv-label {
        margin-bottom: 0.625rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-empty-state {
        border: 1px dashed rgb(229 231 235);
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        padding: 0.875rem 1rem;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        line-height: 1.45;
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-empty-state {
        border-color: rgb(55 65 81);
        background: rgb(31 41 55);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-tag {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.375rem;
        background: rgb(249 250 251);
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 500;
        color: rgb(55 65 81);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-table-wrap {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        overflow-x: auto;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.8125rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-table th {
        background: rgb(249 250 251);
        padding: 0.5rem 0.75rem;
        text-align: left;
        font-weight: 600;
        color: rgb(75 85 99);
        border-bottom: 1px solid rgb(229 231 235);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-table td {
        padding: 0.5rem 0.75rem;
        border-top: 1px solid rgb(243 244 246);
        vertical-align: top;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-table-item {
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-table-sub {
        margin-top: 0.125rem;
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-text-right {
        text-align: right;
        white-space: nowrap;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-total-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        border-radius: 0.5rem;
        background: rgb(243 244 246);
        padding: 0.875rem 1rem;
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-total-bar {
        background: rgb(31 41 55);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-total-label {
        font-size: 0.875rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-total-value {
        font-size: 1.125rem;
        font-weight: 700;
        color: rgb(17 24 39);
        font-variant-numeric: tabular-nums;
    }

    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-total-label,
    .dark .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-total-value {
        color: rgb(243 244 246);
    }

    /* Pricing panels inside view (shared form partials) */
    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-pricing-block {
        margin-bottom: 1rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-pricing-grid {
        margin-bottom: 0;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-pricing-block > div {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        padding: 0.75rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .quotation-view .qv-pricing-block > div > div:first-child {
        font-size: 0.8125rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 0.5rem;
    }

    .fi-resource-quotations.fi-resource-view-record-page .fi-in-entry-wrp-label {
        display: none;
    }

    .fi-resource-quotations.fi-resource-view-record-page .fi-in-section-content {
        padding: 0;
    }

    .fi-resource-quotations.fi-resource-view-record-page .fi-in-section,
    .fi-resource-quotations.fi-resource-view-record-page .fi-in-entry-wrp {
        max-width: none !important;
    }
</style>

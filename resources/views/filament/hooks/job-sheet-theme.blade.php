<style>
    /* Job Sheet list — page header */
    .fi-resource-job-sheets.fi-resource-list-records-page .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .fi-header-subheading {
        display: none;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .fi-page-header-main-ctn {
        margin-bottom: 0.5rem;
    }

    /* Page intro badges */
    .fi-resource-job-sheets.fi-resource-list-records-page .js-page-intro {
        margin-top: -0.25rem;
        margin-bottom: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-page-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-badge-date {
        background: rgb(239 246 255);
        color: rgb(29 78 216);
    }

    /* Filter card */
    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1rem 0 1.125rem;
        grid-column: 1 / -1;
        display: grid;
        grid-template-columns: subgrid;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-page-layout > .js-filter-card {
        margin-bottom: 0;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-grid {
        display: grid;
        grid-column: 1 / -1;
        grid-template-columns: subgrid;
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
        padding-inline: 0.125rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.625rem;
        grid-column: 1 / -1;
        padding-top: 0.125rem;
        padding-right: 1.25rem;
        padding-left: 1.25rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-field:nth-child(1) {
        padding-left: 1.25rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-field:nth-child(6) {
        padding-right: 1.25rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.02);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-input:focus {
        outline: none;
        border-color: rgb(59 130 246);
        box-shadow: 0 0 0 3px rgb(59 130 246 / 0.15);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.375rem;
        padding: 0.4375rem 1.125rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-btn-primary {
        background: rgb(15 23 42);
        color: white;
        border-color: rgb(15 23 42);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-btn-primary:hover {
        background: rgb(30 41 59);
        border-color: rgb(30 41 59);
        color: white;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-btn-secondary:hover {
        background: rgb(249 250 251);
    }

    /* Page layout — shared 6-column grid aligned with filter fields */
    .fi-resource-job-sheets.fi-resource-list-records-page .js-page-layout {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 1rem;
        align-items: start;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel {
        grid-column: 1 / 5;
        min-width: 0;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-panel {
        grid-column: 5 / 7;
        min-width: 0;
        width: 100%;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel,
    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin: 0;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-record-count {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(107 114 128);
        background: rgb(243 244 246);
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
    }

    /* Table inside list panel */
    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-ctn {
        border: none !important;
        border-radius: 0 0 0.75rem 0.75rem !important;
        box-shadow: none !important;
        margin-top: 0 !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-header-ctn {
        display: none;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-table thead th {
        background: rgb(249 250 251);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        padding-top: 0.75rem;
        padding-bottom: 0.75rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-record button {
        cursor: pointer;
        text-align: left;
        width: 100%;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-record button:hover {
        background: rgb(249 250 251);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-record.js-row-selected {
        background: rgb(239 246 255);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-record.js-row-selected button {
        background: transparent;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-table tbody td {
        font-size: 0.875rem;
        color: rgb(31 41 55);
        vertical-align: middle;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-actions-cell .fi-link {
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(37 99 235);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-badge {
        font-size: 0.75rem;
        font-weight: 600;
    }

    /* Detail panel */
    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-panel {
        padding: 1.25rem;
        min-height: 24rem;
        display: flex;
        flex-direction: column;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-kicker {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-status-badge-gray {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-status-badge-info {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-status-badge-success {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-number {
        font-size: 1.375rem;
        font-weight: 700;
        letter-spacing: -0.03em;
        color: rgb(17 24 39);
        margin-bottom: 1.25rem;
        word-break: break-word;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-fields {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
        margin-bottom: 1.25rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-row {
        display: flex;
        flex-direction: row;
        align-items: baseline;
        justify-content: space-between;
        gap: 0.625rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-label {
        flex: 1 1 auto;
        min-width: 0;
        font-size: 0.75rem;
        font-weight: 500;
        letter-spacing: normal;
        text-transform: none;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-value {
        flex: 0 1 auto;
        max-width: 55%;
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(17 24 39);
        text-align: right;
        word-break: break-word;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 1.25rem;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.3125rem 0.625rem;
        border-radius: 0.375rem;
        background: rgb(239 246 255);
        color: rgb(29 78 216);
        font-size: 0.75rem;
        font-weight: 500;
        line-height: 1.3;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-action {
        width: 100%;
        margin-top: auto;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-empty {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        color: rgb(107 114 128);
        font-size: 0.9375rem;
        line-height: 1.5;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-empty strong {
        color: rgb(55 65 81);
        font-weight: 600;
    }

    @media (max-width: 1279px) {
        .fi-resource-job-sheets.fi-resource-list-records-page .js-page-layout {
            grid-template-columns: 1fr;
        }

        .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-card,
        .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel,
        .fi-resource-job-sheets.fi-resource-list-records-page .js-detail-panel {
            grid-column: 1 / -1;
        }

        .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-actions {
            grid-column: 1 / -1;
        }
    }

    @media (max-width: 767px) {
        .fi-resource-job-sheets.fi-resource-list-records-page .js-filter-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Job Sheet view page */
    .fi-resource-job-sheets.fi-resource-view-record-page .fi-header-heading {
        font-size: 1.375rem;
        font-weight: 700;
        letter-spacing: -0.02em;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .fi-in-entry-wrp-label,
    .fi-resource-job-sheets.fi-resource-view-record-page .fi-in-section,
    .fi-resource-job-sheets.fi-resource-view-record-page .fi-in-entry-wrp {
        display: none;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .fi-in-section-content {
        padding: 0;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-view {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        padding-bottom: 5rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1.25rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-info-grid {
        margin-bottom: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-value {
        font-size: 0.9375rem;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-value-strong {
        font-weight: 700;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-value-box {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        padding: 0.625rem 0.75rem;
        background: rgb(249 250 251);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-status-badge-gray {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-status-badge-info {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-status-badge-success {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-stepper-card {
        padding-top: 1.5rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-stepper {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        position: relative;
        margin-bottom: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.625rem;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-step-icon {
        width: 3rem;
        height: 3rem;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgb(243 244 246);
        color: rgb(107 114 128);
        border: 2px solid rgb(229 231 235);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-step-icon svg {
        width: 1.35rem;
        height: 1.35rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-step-active .jsv-step-icon {
        background: rgb(15 23 42);
        color: white;
        border-color: rgb(15 23 42);
        box-shadow: 0 0 0 4px rgb(15 23 42 / 0.12);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-step-done .jsv-step-icon {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
        border-color: rgb(187 247 208);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-step-label {
        font-size: 0.8125rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-step-active .jsv-step-label {
        color: rgb(15 23 42);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-stepper-notes {
        display: grid;
        gap: 0.375rem;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        line-height: 1.45;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-tracking-bar {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        background: rgb(249 250 251);
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        padding: 1rem 1.25rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-tracking-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        margin-bottom: 0.375rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-tracking-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-tracking-success {
        color: rgb(21 128 61);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-summary-row {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem;
        border-top: 1px solid rgb(243 244 246);
        padding-top: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-summary-stat {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-summary-stat strong {
        font-size: 1.75rem;
        line-height: 1;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-summary-stat span {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-assignment-field {
        margin-bottom: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-assignment-label-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.375rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-editable-badge {
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(21 128 61);
        background: rgb(220 252 231);
        border-radius: 9999px;
        padding: 0.1875rem 0.5rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-select {
        width: 100%;
        min-height: 2.5rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-task-group {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        overflow: hidden;
        margin-bottom: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-task-group:last-child {
        margin-bottom: 0;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-task-group-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.75rem 1rem;
        background: rgb(249 250 251);
        border-bottom: 1px solid rgb(229 231 235);
        padding: 0.875rem 1rem;
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: rgb(75 85 99);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-task-group-code {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.375rem;
        padding: 0.25rem 0.625rem;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-task-group-count {
        margin-left: auto;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-table-wrap {
        overflow-x: auto;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-table th {
        background: white;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        text-align: left;
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        white-space: nowrap;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(31 41 55);
        vertical-align: top;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-table tr:last-child td {
        border-bottom: none;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-table-primary {
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-table-secondary {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        margin-top: 0.125rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-action-link {
        display: inline-flex;
        color: rgb(37 99 235);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-action-link svg {
        width: 1.125rem;
        height: 1.125rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-empty {
        color: rgb(107 114 128);
        font-size: 0.875rem;
        padding: 1rem 0;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer {
        position: sticky;
        bottom: 0;
        z-index: 20;
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        align-items: center;
        gap: 1rem;
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 -4px 16px rgb(0 0 0 / 0.06);
        padding: 0.875rem 1.25rem;
        margin-top: 0.5rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer-left {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer-left strong {
        font-size: 0.9375rem;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer-center {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.75rem 1rem;
        font-size: 0.8125rem;
        color: rgb(75 85 99);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.625rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.375rem;
        padding: 0.4375rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-btn-primary {
        background: rgb(15 23 42);
        color: white;
        border-color: rgb(15 23 42);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .fi-ac-btn-action[data-action="saveDraft"] {
        background: rgb(15 23 42) !important;
        color: white !important;
        border: none !important;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-table td:last-child strong {
        color: rgb(17 24 39);
        font-weight: 700;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-card-title {
        margin-bottom: 0;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-search-wrap {
        position: relative;
        flex: 0 1 18rem;
        width: 100%;
        max-width: 18rem;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-search {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem 0.4375rem 2.25rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-search:focus {
        outline: none;
        border-color: rgb(59 130 246);
        box-shadow: 0 0 0 3px rgb(59 130 246 / 0.15);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-sort-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        border: none;
        background: transparent;
        padding: 0;
        font: inherit;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        cursor: pointer;
        white-space: nowrap;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-sort-btn:hover {
        color: rgb(55 65 81);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-sort-btn-active {
        color: rgb(29 78 216);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-sort-icon {
        font-size: 0.75rem;
        line-height: 1;
        opacity: 0.7;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-sort-btn-active .jsv-sort-icon {
        opacity: 1;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-footer {
        grid-template-columns: 1fr auto;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-footer-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem 1.25rem;
        font-size: 0.8125rem;
        color: rgb(75 85 99);
    }

    .fi-resource-job-sheets.fi-resource-view-record-page .jsv-audit-footer-meta strong {
        color: rgb(107 114 128);
        font-weight: 600;
    }

    .fi-resource-job-sheets.fi-resource-view-record-page.jsv-audit-page .fi-header-heading,
    .fi-resource-job-sheets.jsv-audit-page.fi-resource-view-record-page .fi-header-heading {
        font-size: 1.375rem;
        font-weight: 700;
    }

    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-actions-cell .fi-link,
    .fi-resource-job-sheets.fi-resource-list-records-page .js-list-panel .fi-ta-actions-cell .fi-ac-link-action {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(37 99 235);
    }

    @media (max-width: 1279px) {
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-grid-2,
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-tracking-bar,
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-summary-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer {
            grid-template-columns: 1fr;
        }

        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer-center,
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-footer-actions {
            justify-content: flex-start;
        }
    }

    @media (max-width: 767px) {
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-grid-2,
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-tracking-bar,
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-summary-row,
        .fi-resource-job-sheets.fi-resource-view-record-page .jsv-stepper {
            grid-template-columns: 1fr;
        }
    }
</style>

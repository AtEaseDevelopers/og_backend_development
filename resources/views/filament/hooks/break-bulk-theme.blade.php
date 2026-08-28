<style>
    /* Break-Bulk list page */
    .fi-resource-break-bulks.fi-resource-list-records-page .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        margin-top: 0.25rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-page-intro {
        margin-bottom: 1rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-page-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr) auto;
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-search-wrap {
        position: relative;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-input-search {
        padding-left: 2.25rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-actions {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-btn {
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

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-btn-primary {
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-header-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-create-btn {
        text-decoration: none;
        min-height: 2.125rem;
        padding: 0.3125rem 0.875rem;
        font-size: 0.8125rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin: 0;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-record-count {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(107 114 128);
        background: rgb(243 244 246);
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-panel .fi-ta-ctn {
        border: none !important;
        border-radius: 0 0 0.75rem 0.75rem !important;
        box-shadow: none !important;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-panel .fi-ta-header-ctn {
        display: none;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-panel .fi-ta-table thead th {
        background: rgb(249 250 251);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-cell-primary {
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-cell-secondary {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        margin-top: 0.125rem;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-panel .fi-ta-actions-cell {
        text-align: center;
    }

    .fi-resource-break-bulks.fi-resource-list-records-page .bb-list-panel .fi-ta-actions-cell .fi-link {
        color: rgb(55 65 81);
    }

    /* Break-Bulk view page */
    .fi-resource-break-bulks.fi-resource-view-record-page .fi-header-heading {
        font-size: 1.375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .fi-infolist {
        gap: 0 !important;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-view {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-header-badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-meta-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3125rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 500;
        background: rgb(243 244 246);
        color: rgb(55 65 81);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3125rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-status-badge-info {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-status-badge-success {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-status-badge-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1.25rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 1rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-grid {
        display: grid;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-grid-top {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-grid-middle {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        padding-top: 1rem;
        border-top: 1px solid rgb(243 244 246);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-grid-bottom {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        padding-top: 1rem;
        border-top: 1px solid rgb(243 244 246);
        margin-bottom: 0;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-item {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-label-danger {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        color: rgb(220 38 38);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-label-danger svg {
        width: 1rem;
        height: 1rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
        word-break: break-word;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-value-icon {
        display: inline-flex;
        align-items: flex-start;
        gap: 0.375rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-value-icon svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        margin-top: 0.125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        width: fit-content;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-badge-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photos-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
        margin-bottom: 0.75rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photo-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photo-img,
    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photo-placeholder {
        aspect-ratio: 4 / 3;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        background: rgb(249 250 251);
        overflow: hidden;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photo-img {
        width: 100%;
        object-fit: cover;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photo-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgb(156 163 175);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photo-placeholder svg {
        width: 2rem;
        height: 2rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photo-label {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photos-meta {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        gap: 1rem;
        align-items: stretch;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-card {
        position: relative;
        margin-bottom: 0;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-tag {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 0.75rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-tag-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-tag-dark {
        background: rgb(17 24 39);
        color: white;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-assign-row {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
        margin-bottom: 0.75rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-assign-row svg {
        width: 1.125rem;
        height: 1.125rem;
        color: rgb(107 114 128);
        flex-shrink: 0;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-footer {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgb(243 244 246);
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-arrow {
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgb(156 163 175);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-arrow svg {
        width: 1.5rem;
        height: 1.5rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-form-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        margin-bottom: 0.875rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-form-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-form-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-info-box {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        padding: 0.75rem;
        border-radius: 0.5rem;
        background: rgb(239 246 255);
        color: rgb(29 78 216);
        font-size: 0.8125rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-info-box svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        margin-top: 0.125rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-audit-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-audit-card-title {
        margin-bottom: 0;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-audit-search-wrap {
        position: relative;
        flex: 0 1 18rem;
        max-width: 18rem;
        width: 100%;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-audit-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-audit-search {
        width: 100%;
        min-height: 2.25rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.375rem 0.75rem 0.375rem 2.25rem;
        font-size: 0.875rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-table-wrap {
        overflow-x: auto;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-table th {
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

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(31 41 55);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-sort-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        background: none;
        border: none;
        padding: 0;
        font: inherit;
        color: inherit;
        cursor: pointer;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-sort-btn-active {
        color: rgb(29 78 216);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-sort-icon {
        font-size: 0.75rem;
        opacity: 0.7;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-type-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-empty {
        text-align: center;
        color: rgb(107 114 128);
        padding: 1.5rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-footer {
        position: sticky;
        bottom: 0;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 -4px 16px rgb(0 0 0 / 0.06);
        padding: 0.875rem 1.25rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-footer-left {
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-footer-left strong {
        font-size: 0.9375rem;
        color: rgb(17 24 39);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-footer-actions {
        display: flex;
        gap: 0.625rem;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-btn {
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

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-btn-primary {
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .bbv-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-resource-break-bulks.fi-resource-view-record-page .fi-ac-btn-action[data-action="saveReassignment"] {
        background: rgb(15 23 42) !important;
        color: white !important;
    }

    @media (max-width: 1024px) {
        .fi-resource-break-bulks.fi-resource-list-records-page .bb-filter-grid {
            grid-template-columns: 1fr;
        }

        .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-grid-top,
        .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-grid-middle,
        .fi-resource-break-bulks.fi-resource-view-record-page .bbv-ref-grid-bottom {
            grid-template-columns: 1fr 1fr;
        }

        .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment {
            grid-template-columns: 1fr;
        }

        .fi-resource-break-bulks.fi-resource-view-record-page .bbv-reassignment-arrow {
            transform: rotate(90deg);
        }

        .fi-resource-break-bulks.fi-resource-view-record-page .bbv-photos-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

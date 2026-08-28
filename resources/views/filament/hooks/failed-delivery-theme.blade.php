<style>
    .fi-resource-failed-deliveries.fi-resource-list-records-page .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        margin-top: 0.25rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-page-intro {
        margin-bottom: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-page-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-badge-date {
        background: rgb(239 246 255);
        color: rgb(29 78 216);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1rem 1.25rem;
        margin-bottom: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 0.875rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-form {
        display: flex;
        flex-direction: column;
        gap: 0.875rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-grid-top {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-grid-bottom {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.875rem 1rem;
        align-items: end;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-status {
        min-height: 2.375rem;
        display: flex;
        align-items: center;
        padding: 0.4375rem 0.75rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: rgb(254 242 242);
        font-size: 0.875rem;
        font-weight: 700;
        color: rgb(185 28 28);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-search-wrap {
        position: relative;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-input-search {
        padding-left: 2.25rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-actions {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        padding-bottom: 0.125rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-btn {
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

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-btn-primary {
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-list-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        overflow: hidden;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-list-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-list-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin: 0;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-record-count {
        font-size: 0.8125rem;
        font-weight: 600;
        color: rgb(107 114 128);
        background: rgb(243 244 246);
        border-radius: 9999px;
        padding: 0.25rem 0.75rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-table-wrap {
        overflow-x: auto;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-table th {
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

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(31 41 55);
        vertical-align: middle;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-table tr:last-child td {
        border-bottom: none;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-cell-strong {
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-do-link {
        color: rgb(29 78 216);
        font-weight: 600;
        text-decoration: none;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-do-link:hover {
        text-decoration: underline;
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-empty {
        text-align: center;
        color: rgb(107 114 128);
        padding: 2rem 1rem;
    }

    @media (max-width: 1024px) {
        .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-grid-top {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-grid-bottom {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 640px) {
        .fi-resource-failed-deliveries.fi-resource-list-records-page .fd-filter-grid-top {
            grid-template-columns: 1fr;
        }
    }

    /* Failed delivery view / reassignment page */
    .fi-resource-failed-deliveries.fi-resource-view-record-page .fi-header-heading {
        font-size: 1.375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fi-infolist {
        gap: 0 !important;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-view {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-header-badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-meta-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3125rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 500;
        background: rgb(243 244 246);
        color: rgb(55 65 81);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3125rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 600;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-status-badge-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1.25rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-card-title-inline,
    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-audit-card-title {
        margin-bottom: 0;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-card-meta {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        text-align: right;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-grid {
        display: grid;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-grid-top {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-grid-middle {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        padding-top: 1rem;
        border-top: 1px solid rgb(243 244 246);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-grid-bottom {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        padding-top: 1rem;
        border-top: 1px solid rgb(243 244 246);
        margin-bottom: 0;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-item {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-label-danger {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        color: rgb(220 38 38);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-label-danger svg {
        width: 1rem;
        height: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
        word-break: break-word;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-sub {
        font-weight: 500;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-value-icon {
        display: inline-flex;
        align-items: flex-start;
        gap: 0.375rem;
        flex-wrap: wrap;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-value-icon svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        margin-top: 0.125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        width: fit-content;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-badge-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photos-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photo-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photo-img,
    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photo-placeholder {
        aspect-ratio: 4 / 3;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        background: rgb(249 250 251);
        overflow: hidden;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photo-img {
        width: 100%;
        object-fit: cover;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photo-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgb(156 163 175);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photo-placeholder svg {
        width: 2rem;
        height: 2rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photo-label {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-card {
        display: block;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.75rem;
        padding: 1rem;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-card-selected {
        border-color: rgb(15 23 42);
        box-shadow: 0 0 0 3px rgb(15 23 42 / 0.08);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-radio {
        margin-bottom: 0.625rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 0.375rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-description {
        font-size: 0.8125rem;
        color: rgb(75 85 99);
        margin-bottom: 0.75rem;
        line-height: 1.45;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-commission {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        padding: 0.75rem;
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        font-size: 0.8125rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-commission-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-commission-note {
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-driver-note {
        color: rgb(29 78 216);
        font-weight: 600;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
        gap: 1rem;
        align-items: stretch;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-card {
        margin-bottom: 0;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-tag {
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

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-tag-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-tag-dark {
        background: rgb(17 24 39);
        color: white;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-assign-row {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
        margin-bottom: 0.75rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-assign-row svg {
        width: 1.125rem;
        height: 1.125rem;
        color: rgb(107 114 128);
        flex-shrink: 0;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-footer {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgb(243 244 246);
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-arrow {
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgb(156 163 175);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-arrow svg {
        width: 1.5rem;
        height: 1.5rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-form-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        margin-bottom: 0.875rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-form-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-form-input {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-audit-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-audit-search-wrap {
        position: relative;
        flex: 0 1 18rem;
        max-width: 18rem;
        width: 100%;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-audit-search-icon {
        position: absolute;
        left: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-audit-search {
        width: 100%;
        min-height: 2.25rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        padding: 0.375rem 0.75rem 0.375rem 2.25rem;
        font-size: 0.875rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-table-wrap {
        overflow-x: auto;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-table th {
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

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(31 41 55);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-sort-btn {
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

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-sort-btn-active {
        color: rgb(29 78 216);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-badge {
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

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-empty {
        text-align: center;
        color: rgb(107 114 128);
        padding: 1.5rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.625rem;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-btn {
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

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-btn-primary {
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-btn-secondary {
        background: white;
        color: rgb(55 65 81);
        border-color: rgb(209 213 219);
    }

    .fi-resource-failed-deliveries.fi-resource-view-record-page .fi-ac-btn-action[data-action="saveReassignment"] {
        background: rgb(15 23 42) !important;
        color: white !important;
    }

    @media (max-width: 1024px) {
        .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-grid-top,
        .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-ref-grid-bottom {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-type-grid,
        .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment {
            grid-template-columns: 1fr;
        }

        .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-reassignment-arrow {
            transform: rotate(90deg);
        }

        .fi-resource-failed-deliveries.fi-resource-view-record-page .fdv-photos-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

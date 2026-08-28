<style>
    .fi-resource-delivery-orders.fi-resource-view-record-page .fi-header-heading {
        font-size: 1.375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .fi-infolist {
        gap: 0 !important;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-view {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-header-badges {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-meta-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.3125rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 500;
        background: rgb(243 244 246);
        color: rgb(55 65 81);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.3125rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 700;
        letter-spacing: 0.03em;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge svg {
        width: 1rem;
        height: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge-success {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge-gray {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge-warning {
        background: rgb(254 243 199);
        color: rgb(180 83 9);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge-info {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-status-badge-primary {
        background: rgb(243 232 255);
        color: rgb(126 34 206);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1.25rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-card-header .dtv-card-title {
        margin-bottom: 0;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-card-meta {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        text-align: right;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-overview-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-overview-item {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-overview-destination {
        grid-column: span 1;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
        word-break: break-word;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-value-box {
        display: inline-flex;
        width: fit-content;
        padding: 0.25rem 0.625rem;
        border-radius: 0.375rem;
        background: rgb(243 244 246);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-value-icon {
        display: inline-flex;
        align-items: flex-start;
        gap: 0.375rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-value-icon svg {
        width: 1rem;
        height: 1rem;
        flex-shrink: 0;
        margin-top: 0.125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photos-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photo-item {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photo-img,
    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photo-placeholder {
        aspect-ratio: 4 / 3;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        background: rgb(249 250 251);
        overflow: hidden;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photo-img {
        width: 100%;
        object-fit: cover;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photo-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgb(156 163 175);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photo-placeholder svg {
        width: 2rem;
        height: 2rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photo-label {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-detail-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-document-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-document-row:last-child {
        border-bottom: none;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-document-name {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-document-meta {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-document-actions {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-doc-badge {
        display: inline-flex;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-download-btn {
        display: inline-flex;
        color: rgb(55 65 81);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-download-btn svg {
        width: 1.125rem;
        height: 1.125rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-signature-block {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-signature-box {
        min-height: 7rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-signature-img {
        max-width: 100%;
        max-height: 5rem;
        object-fit: contain;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-signature-placeholder {
        font-size: 0.875rem;
        color: rgb(156 163 175);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-signature-time {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-gps-card {
        display: flex;
        gap: 0.875rem;
        padding: 1rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        margin-bottom: 1rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-gps-icon-wrap svg {
        width: 1.5rem;
        height: 1.5rem;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-gps-location {
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-gps-coords {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        margin-top: 0.25rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-meta-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.875rem;
        margin-bottom: 0.75rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-meta-grid > div {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-sync-table {
        display: flex;
        flex-direction: column;
        gap: 0.625rem;
        margin-bottom: 0.75rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-sync-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.875rem;
        color: rgb(75 85 99);
        padding-bottom: 0.625rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-sync-row:last-child {
        border-bottom: none;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-sync-row strong {
        color: rgb(17 24 39);
        font-weight: 600;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-sync-badge {
        display: inline-flex;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-footnote {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        margin: 0;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-empty {
        font-size: 0.875rem;
        color: rgb(107 114 128);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-table-wrap {
        overflow-x: auto;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-table th {
        background: rgb(249 250 251);
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        text-align: left;
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(229 231 235);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid rgb(243 244 246);
        color: rgb(31 41 55);
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-event-highlight {
        color: rgb(21 128 61);
        font-weight: 600;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-footer {
        display: flex;
        justify-content: flex-end;
    }

    .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.375rem;
        padding: 0.4375rem 1rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        text-decoration: none;
        border: 1px solid rgb(209 213 219);
        background: white;
        color: rgb(55 65 81);
    }

    .fi-page-delivery-monitoring .dm-do-link {
        color: rgb(29 78 216);
        font-weight: 600;
        text-decoration: none;
    }

    .fi-page-delivery-monitoring .dm-do-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 1024px) {
        .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-overview-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-detail-grid {
            grid-template-columns: 1fr;
        }

        .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-photos-grid {
            grid-template-columns: 1fr;
        }

        .fi-resource-delivery-orders.fi-resource-view-record-page .dtv-meta-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

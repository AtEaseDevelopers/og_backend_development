<style>
    .fi-page-returned-csn-desk .fi-header-heading {
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-page-returned-csn-desk .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        margin-top: 0.25rem;
    }

    .fi-page-returned-csn-desk .rcsn-page-intro {
        margin-bottom: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-page-badges {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .fi-page-returned-csn-desk .rcsn-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.1875rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-page-returned-csn-desk .rcsn-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-page-returned-csn-desk .rcsn-badge-date {
        background: rgb(239 246 255);
        color: rgb(29 78 216);
    }

    .fi-page-returned-csn-desk .rcsn-scan-card,
    .fi-page-returned-csn-desk .rcsn-detail-card {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        padding: 1.25rem;
        margin-bottom: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-listing-section {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .fi-page-returned-csn-desk .rcsn-scan-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-card-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin: 0;
    }

    .fi-page-returned-csn-desk .rcsn-located-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        background: rgb(220 252 231);
        color: rgb(21 128 61);
        font-size: 0.75rem;
        font-weight: 600;
    }

    .fi-page-returned-csn-desk .rcsn-located-badge-danger {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-page-returned-csn-desk .rcsn-located-icon {
        width: 1rem;
        height: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-scan-body {
        display: grid;
        grid-template-columns: 8.5rem minmax(0, 1fr);
        gap: 1.25rem;
        align-items: start;
    }

    .fi-page-returned-csn-desk .rcsn-qr-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        min-height: 8.5rem;
        border: 2px dashed rgb(209 213 219);
        border-radius: 0.75rem;
        background: rgb(249 250 251);
        color: rgb(107 114 128);
    }

    .fi-page-returned-csn-desk .rcsn-qr-icon {
        width: 2.5rem;
        height: 2.5rem;
    }

    .fi-page-returned-csn-desk .rcsn-qr-label {
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
    }

    .fi-page-returned-csn-desk .rcsn-scan-input-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .fi-page-returned-csn-desk .rcsn-field-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-page-returned-csn-desk .rcsn-scan-input {
        width: 100%;
        min-height: 2.75rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.5rem 0.875rem;
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-page-returned-csn-desk .rcsn-scan-info {
        display: flex;
        align-items: flex-start;
        gap: 0.5rem;
        margin-top: 1rem;
        padding: 0.75rem 0.875rem;
        border-radius: 0.5rem;
        background: rgb(239 246 255);
        color: rgb(29 78 216);
        font-size: 0.8125rem;
        line-height: 1.45;
    }

    .fi-page-returned-csn-desk .rcsn-info-icon {
        width: 1.125rem;
        height: 1.125rem;
        flex-shrink: 0;
        margin-top: 0.0625rem;
    }

    .fi-page-returned-csn-desk .rcsn-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 1rem 1.25rem;
        margin-top: 1rem;
        margin-bottom: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-detail-cell {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
        min-width: 0;
    }

    .fi-page-returned-csn-desk .rcsn-detail-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    .fi-page-returned-csn-desk .rcsn-detail-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
        word-break: break-word;
    }

    .fi-page-returned-csn-desk .rcsn-detail-muted {
        font-size: 0.8125rem;
        font-weight: 500;
        color: rgb(107 114 128);
    }

    .fi-page-returned-csn-desk .rcsn-detail-select {
        width: 100%;
        min-height: 2.375rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.4375rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-page-returned-csn-desk .rcsn-edit-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1.25rem;
        margin-bottom: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-toggle-field {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        color: rgb(55 65 81);
    }

    .fi-page-returned-csn-desk .rcsn-toggle-input {
        width: 1rem;
        height: 1rem;
        accent-color: rgb(30 58 138);
    }

    .fi-page-returned-csn-desk .rcsn-remarks-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-remarks-input {
        width: 100%;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.625rem 0.875rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
        resize: vertical;
    }

    .fi-page-returned-csn-desk .rcsn-status-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.625rem;
    }

    .fi-page-returned-csn-desk .rcsn-status-tag {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    .fi-page-returned-csn-desk .rcsn-status-tag-label {
        font-weight: 600;
        opacity: 0.85;
    }

    .fi-page-returned-csn-desk .rcsn-status-tag-success {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-page-returned-csn-desk .rcsn-status-tag-info {
        background: rgb(224 231 255);
        color: rgb(67 56 202);
    }

    .fi-page-returned-csn-desk .rcsn-status-tag-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-page-returned-csn-desk .rcsn-commission-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.875rem 1rem;
        border-radius: 0.75rem;
        background: rgb(220 252 231);
        border: 1px solid rgb(187 247 208);
        margin-bottom: 1rem;
    }

    .fi-page-returned-csn-desk .rcsn-commission-left {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(21 128 61);
    }

    .fi-page-returned-csn-desk .rcsn-commission-icon {
        width: 1.125rem;
        height: 1.125rem;
    }

    .fi-page-returned-csn-desk .rcsn-commission-related {
        font-size: 0.8125rem;
        color: rgb(75 85 99);
    }

    .fi-page-returned-csn-desk .rcsn-secondary-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding-top: 0.5rem;
    }

    .fi-page-returned-csn-desk .rcsn-flag-missing-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 2.375rem;
        padding: 0.4375rem 0.875rem;
        border-radius: 0.5rem;
        border: 1px solid rgb(251 191 36);
        background: rgb(255 251 235);
        color: rgb(180 83 9);
        font-size: 0.875rem;
        font-weight: 600;
    }

    .fi-page-returned-csn-desk .rcsn-flag-missing-btn:hover {
        background: rgb(254 243 199);
    }

    .fi-page-returned-csn-desk .rcsn-grace-note {
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    @media (max-width: 1024px) {
        .fi-page-returned-csn-desk .rcsn-detail-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 640px) {
        .fi-page-returned-csn-desk .rcsn-scan-body {
            grid-template-columns: 1fr;
        }

        .fi-page-returned-csn-desk .rcsn-detail-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

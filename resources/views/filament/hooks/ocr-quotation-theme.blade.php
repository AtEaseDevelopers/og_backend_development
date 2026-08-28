<style>
    .fi-resource-ocr-uploads.fi-resource-list-records-page .fi-header-heading {
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.025em;
        color: rgb(17 24 39);
    }

    .fi-resource-ocr-uploads.fi-resource-list-records-page .fi-header-subheading {
        font-size: 0.9375rem;
        color: rgb(107 114 128);
        max-width: 42rem;
    }

    .fi-resource-ocr-uploads .ocr-page-intro {
        margin-bottom: 1rem;
    }

    .fi-resource-ocr-uploads .ocr-badge {
        display: inline-flex;
        align-items: center;
        border-radius: 9999px;
        padding: 0.1875rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .fi-resource-ocr-uploads .ocr-badge-muted {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-ocr-uploads .ocr-dashboard {
        display: grid;
        grid-template-columns: 0.95fr 1.05fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-ocr-uploads .ocr-upload-card,
    .fi-resource-ocr-uploads .ocr-queue-card,
    .fi-resource-ocr-uploads .ocr-verify-panel {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px rgb(0 0 0 / 0.04);
    }

    .fi-resource-ocr-uploads .ocr-upload-card,
    .fi-resource-ocr-uploads .ocr-queue-card {
        padding: 1.125rem;
    }

    .fi-resource-ocr-uploads .ocr-card-title {
        margin: 0 0 0.375rem;
        font-size: 1rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-ocr-uploads .ocr-card-note {
        margin: 0 0 1rem;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-ocr-uploads .ocr-dropzone {
        display: block;
        cursor: pointer;
    }

    .fi-resource-ocr-uploads .ocr-dropzone-input {
        display: none;
    }

    .fi-resource-ocr-uploads .ocr-dropzone-inner {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        min-height: 10rem;
        border: 2px dashed rgb(209 213 219);
        border-radius: 0.75rem;
        background: rgb(249 250 251);
        padding: 1.5rem;
        text-align: center;
    }

    .fi-resource-ocr-uploads .ocr-dropzone-inner svg {
        width: 2rem;
        height: 2rem;
        color: rgb(107 114 128);
    }

    .fi-resource-ocr-uploads .ocr-dropzone-title {
        font-size: 0.9375rem;
        font-weight: 600;
        color: rgb(17 24 39);
    }

    .fi-resource-ocr-uploads .ocr-dropzone-sub {
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .fi-resource-ocr-uploads .ocr-upload-actions {
        margin-top: 1rem;
    }

    .fi-resource-ocr-uploads .ocr-table-wrap,
    .fi-resource-ocr-uploads .ocr-lines-table-wrap {
        overflow-x: auto;
    }

    .fi-resource-ocr-uploads .ocr-table,
    .fi-resource-ocr-uploads .ocr-lines-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.875rem;
    }

    .fi-resource-ocr-uploads .ocr-table th,
    .fi-resource-ocr-uploads .ocr-lines-table th {
        padding: 0.625rem 0.75rem;
        text-align: left;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        border-bottom: 1px solid rgb(229 231 235);
    }

    .fi-resource-ocr-uploads .ocr-table td,
    .fi-resource-ocr-uploads .ocr-lines-table td {
        padding: 0.75rem;
        border-bottom: 1px solid rgb(243 244 246);
        vertical-align: middle;
        color: rgb(17 24 39);
    }

    .fi-resource-ocr-uploads .ocr-file-link {
        font-weight: 600;
        color: rgb(29 78 216);
        text-align: left;
        background: none;
        border: none;
        padding: 0;
    }

    .fi-resource-ocr-uploads .ocr-file-name {
        font-weight: 600;
    }

    .fi-resource-ocr-uploads .ocr-status-pill {
        display: inline-flex;
        border-radius: 9999px;
        padding: 0.1875rem 0.625rem;
        font-size: 0.6875rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .fi-resource-ocr-uploads .ocr-status-pending {
        background: rgb(220 252 231);
        color: rgb(21 128 61);
    }

    .fi-resource-ocr-uploads .ocr-status-completed {
        background: rgb(219 234 254);
        color: rgb(29 78 216);
    }

    .fi-resource-ocr-uploads .ocr-status-draft {
        background: rgb(243 244 246);
        color: rgb(75 85 99);
    }

    .fi-resource-ocr-uploads .ocr-status-extracting {
        display: grid;
        gap: 0.375rem;
        min-width: 9rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: rgb(234 88 12);
    }

    .fi-resource-ocr-uploads .ocr-status-extracting small {
        font-size: 0.6875rem;
        font-weight: 500;
        color: rgb(107 114 128);
    }

    .fi-resource-ocr-uploads .ocr-status-failed {
        background: rgb(254 226 226);
        color: rgb(185 28 28);
    }

    .fi-resource-ocr-uploads .ocr-preview-image,
    .fi-resource-ocr-uploads .ocr-preview-frame {
        width: 100%;
        min-height: 24rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        background: white;
    }

    .fi-resource-ocr-uploads .ocr-preview-image {
        object-fit: contain;
    }

    .fi-resource-ocr-uploads .ocr-progress-track {
        height: 0.375rem;
        border-radius: 9999px;
        background: rgb(254 215 170);
        overflow: hidden;
    }

    .fi-resource-ocr-uploads .ocr-progress-bar {
        height: 100%;
        border-radius: 9999px;
        background: rgb(234 88 12);
    }

    .fi-resource-ocr-uploads .ocr-empty {
        padding: 2rem 1rem !important;
        text-align: center;
        color: rgb(107 114 128);
    }

    .fi-resource-ocr-uploads .ocr-btn {
        border-radius: 0.5rem;
        min-height: 2.375rem;
        padding: 0.5rem 0.875rem;
        font-size: 0.8125rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .fi-resource-ocr-uploads .ocr-btn-sm {
        min-height: 2rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
    }

    .fi-resource-ocr-uploads .ocr-btn-primary {
        border: none;
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-ocr-uploads .ocr-btn-outline {
        border: 1px solid rgb(209 213 219);
        background: white;
        color: rgb(55 65 81);
    }

    .fi-resource-ocr-uploads .ocr-btn-danger-text {
        border: none;
        background: transparent;
        color: rgb(220 38 38);
        padding-left: 0;
    }

    .fi-resource-ocr-uploads .ocr-verify-overlay {
        position: fixed;
        inset: 0;
        z-index: 40;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        background: rgb(15 23 42 / 0.45);
    }

    .fi-resource-ocr-uploads .ocr-verify-panel {
        width: min(1120px, 100%);
        max-height: calc(100vh - 3rem);
        overflow: auto;
        padding: 1.25rem;
    }

    .fi-resource-ocr-uploads .ocr-verify-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
    }

    .fi-resource-ocr-uploads .ocr-verify-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-ocr-uploads .ocr-verify-head-actions {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .fi-resource-ocr-uploads .ocr-confidence-badge {
        display: inline-flex;
        border-radius: 9999px;
        background: rgb(220 252 231);
        color: rgb(21 128 61);
        padding: 0.25rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .fi-resource-ocr-uploads .ocr-close-btn {
        width: 2rem;
        height: 2rem;
        border: 1px solid rgb(209 213 219);
        border-radius: 9999px;
        background: white;
        font-size: 1.25rem;
        line-height: 1;
        color: rgb(75 85 99);
    }

    .fi-resource-ocr-uploads .ocr-verify-grid {
        display: grid;
        grid-template-columns: 0.95fr 1.05fr;
        gap: 1rem;
    }

    .fi-resource-ocr-uploads .ocr-preview-card,
    .fi-resource-ocr-uploads .ocr-fields-card {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        background: rgb(249 250 251);
        padding: 1rem;
    }

    .fi-resource-ocr-uploads .ocr-preview-paper {
        min-height: 24rem;
        border-radius: 0.5rem;
        background: white;
        border: 1px solid rgb(229 231 235);
        padding: 1rem;
    }

    .fi-resource-ocr-uploads .ocr-preview-header {
        margin-bottom: 1rem;
        font-size: 0.875rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        color: rgb(107 114 128);
    }

    .fi-resource-ocr-uploads .ocr-preview-block {
        margin-bottom: 0.75rem;
        padding: 0.625rem 0.75rem;
        border-radius: 0.375rem;
        border: 1px solid transparent;
    }

    .fi-resource-ocr-uploads .ocr-preview-green {
        background: rgb(240 253 244);
        border-color: rgb(187 247 208);
    }

    .fi-resource-ocr-uploads .ocr-preview-blue {
        background: rgb(239 246 255);
        border-color: rgb(191 219 254);
    }

    .fi-resource-ocr-uploads .ocr-preview-red {
        background: rgb(254 242 242);
        border-color: rgb(254 202 202);
    }

    .fi-resource-ocr-uploads .ocr-preview-label {
        display: block;
        font-size: 0.625rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        margin-bottom: 0.125rem;
    }

    .fi-resource-ocr-uploads .ocr-preview-text {
        font-size: 0.8125rem;
        color: rgb(17 24 39);
        white-space: pre-line;
    }

    .fi-resource-ocr-uploads .ocr-field-section + .ocr-field-section {
        margin-top: 1.25rem;
    }

    .fi-resource-ocr-uploads .ocr-section-title {
        margin: 0 0 0.75rem;
        font-size: 0.875rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-ocr-uploads .ocr-field + .ocr-field {
        margin-top: 0.875rem;
    }

    .fi-resource-ocr-uploads .ocr-field-label {
        display: block;
        margin-bottom: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: rgb(55 65 81);
    }

    .fi-resource-ocr-uploads .ocr-field-input-wrap {
        position: relative;
    }

    .fi-resource-ocr-uploads .ocr-field-input,
    .fi-resource-ocr-uploads .ocr-field-textarea,
    .fi-resource-ocr-uploads .ocr-line-input {
        width: 100%;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: white;
        padding: 0.625rem 0.75rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-resource-ocr-uploads .ocr-field-verified .ocr-field-input {
        padding-right: 2.25rem;
    }

    .fi-resource-ocr-uploads .ocr-verified-icon {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: rgb(22 163 74);
        font-weight: 700;
    }

    .fi-resource-ocr-uploads .ocr-lines-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .fi-resource-ocr-uploads .ocr-line-input-sm {
        max-width: 6rem;
    }

    .fi-resource-ocr-uploads .ocr-line-action {
        width: 2rem;
        text-align: center;
    }

    .fi-resource-ocr-uploads .ocr-line-remove {
        border: none;
        background: none;
        color: rgb(220 38 38);
        font-size: 1.125rem;
        line-height: 1;
    }

    .fi-resource-ocr-uploads .ocr-verify-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid rgb(229 231 235);
    }

    .fi-resource-ocr-uploads .ocr-verify-footer-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    @media (max-width: 1023px) {
        .fi-resource-ocr-uploads .ocr-dashboard,
        .fi-resource-ocr-uploads .ocr-verify-grid {
            grid-template-columns: 1fr;
        }

        .fi-resource-ocr-uploads .ocr-verify-footer {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>

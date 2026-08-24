<style>
    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view {
        width: 100%;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        line-height: 1.3;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-value {
        color: rgb(17 24 39);
        font-size: 0.875rem;
        line-height: 1.45;
        min-height: 1.25rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-field {
        display: flex;
        flex-direction: column;
        gap: 0.375rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-field-stack:not(.csn-charges-stack) > .csn-field {
        padding-bottom: 0.875rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-field-stack:not(.csn-charges-stack) > .csn-field:not(:last-child) {
        border-bottom: 1px solid rgb(243 244 246);
        margin-bottom: 0.125rem;
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-field-stack:not(.csn-charges-stack) > .csn-field:not(:last-child) {
        border-bottom-color: rgb(55 65 81);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-4 > .csn-field,
    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-3 > .csn-field {
        min-height: 2.75rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-charges-stack > .csn-field {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 0.75rem 1.5rem;
        align-items: baseline;
        padding: 0.5rem 0;
        border-bottom: 1px solid rgb(243 244 246);
        margin-bottom: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-charges-stack > .csn-field:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-charges-stack .csn-value {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-value {
        color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-2,
    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-3,
    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-4 {
        display: grid;
        gap: 1rem;
        width: 100%;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-pair {
        align-items: stretch;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-pair > .csn-card {
        height: 100%;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card > .csn-grid-2 > .csn-field-stack:first-child {
        padding-right: 1rem;
        border-right: 1px solid rgb(243 244 246);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card > .csn-grid-2 > .csn-field-stack:first-child {
        border-right-color: rgb(55 65 81);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card > .csn-grid-2 > .csn-field-stack:last-child {
        padding-left: 0.25rem;
    }

    @media (max-width: 767px) {
        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card > .csn-grid-2 > .csn-field-stack:first-child {
            padding-right: 0;
            border-right: none;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgb(243 244 246);
        }

        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card > .csn-grid-2 > .csn-field-stack:last-child {
            padding-left: 0;
            padding-top: 0.25rem;
        }
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-equal {
        align-items: stretch;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-equal > .csn-card {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-fill {
        flex: 1;
        min-height: 0;
        overflow: auto;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-equal .csn-card-body > div {
        flex: 1;
        min-height: 0;
        height: 100%;
        overflow: auto;
        box-sizing: border-box;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-equal .csn-card-title {
        flex-shrink: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-equal .csn-empty-state {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 12rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-4 {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    @media (max-width: 1023px) {
        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-4 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767px) {
        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-2,
        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-3,
        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-grid-4 {
            grid-template-columns: 1fr;
        }
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-span-2 {
        grid-column: span 2;
    }

    @media (max-width: 767px) {
        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-span-2 {
            grid-column: span 1;
        }
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-field-stack {
        display: flex;
        flex-direction: column;
        gap: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-field-stack .csn-grid-3 {
        margin-top: 0.25rem;
        padding-top: 0.875rem;
        border-top: 1px solid rgb(243 244 246);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-field-stack .csn-grid-3 {
        border-top-color: rgb(55 65 81);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-divider {
        border-top: 1px solid rgb(229 231 235);
        margin: 0.75rem 0;
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-divider {
        border-top-color: rgb(55 65 81);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-subsection-title {
        font-size: 0.8125rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin: 0.25rem 0 0.625rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        background: white;
        padding: 1rem 1.25rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card {
        border-color: rgb(55 65 81);
        background: rgb(17 24 39);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
        margin-bottom: 0.875rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid rgb(243 244 246);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-card-title {
        color: rgb(243 244 246);
        border-bottom-color: rgb(55 65 81);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-task-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-top: 0.25rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-task-badge {
        border-radius: 0.25rem;
        background: rgb(243 244 246);
        padding: 0.125rem 0.5rem;
        font-size: 0.6875rem;
        font-weight: 500;
        color: rgb(75 85 99);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-task-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-card {
        padding-top: 1.125rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-header {
        display: flex;
        align-items: flex-start;
        gap: 0.625rem;
        margin-bottom: 1.25rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-header-icon {
        flex-shrink: 0;
        margin-top: 0.125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-header-icon svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-header-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
        line-height: 1.3;
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-header-title {
        color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-header-desc {
        margin-top: 0.25rem;
        font-size: 0.8125rem;
        line-height: 1.45;
        color: rgb(107 114 128);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-timeline {
        display: flex;
        flex-direction: column;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step {
        display: flex;
        align-items: flex-start;
        gap: 0.875rem;
        position: relative;
        padding-bottom: 1.375rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step:last-child {
        padding-bottom: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step:not(:last-child) .csn-trace-rail::after {
        content: '';
        position: absolute;
        top: 2rem;
        left: 50%;
        bottom: -0.375rem;
        width: 2px;
        transform: translateX(-50%);
        background: rgb(229 231 235);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step:not(:last-child) .csn-trace-rail::after {
        background: rgb(55 65 81);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-rail {
        position: relative;
        flex-shrink: 0;
        width: 2rem;
        display: flex;
        justify-content: center;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-bubble {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 9999px;
        background: rgb(243 244 246);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-bubble {
        background: rgb(31 41 55);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-bubble svg {
        width: 1rem;
        height: 1rem;
        color: rgb(107 114 128);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step-active .csn-trace-bubble {
        background: rgb(15 23 42);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step-active .csn-trace-bubble svg {
        color: white;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-content {
        min-width: 0;
        padding-top: 0.125rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-label {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
        line-height: 1.3;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step-active .csn-trace-label {
        color: rgb(15 23 42);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step-active .csn-trace-label {
        color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-value {
        margin-top: 0.125rem;
        font-size: 0.875rem;
        font-weight: 600;
        color: rgb(17 24 39);
        line-height: 1.35;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-link {
        color: inherit;
        font-weight: inherit;
        text-decoration: none;
        transition: color 0.15s ease;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-link:hover {
        color: rgb(13 148 136);
        text-decoration: underline;
        text-underline-offset: 2px;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step-active .csn-trace-link:hover {
        color: rgb(13 148 136);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-value {
        color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step-active .csn-trace-value {
        font-weight: 700;
        color: rgb(15 23 42);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-trace-step-active .csn-trace-value {
        color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-card {
        padding-top: 1.125rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: 1rem;
        flex-wrap: wrap;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-header-left {
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-header-icon {
        color: rgb(107 114 128);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-header-icon svg {
        width: 1.25rem;
        height: 1.25rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-header-title {
        font-size: 0.9375rem;
        font-weight: 700;
        color: rgb(17 24 39);
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-header-title {
        color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-header-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-btn {
        border: none;
        border-radius: 0.375rem;
        background: rgb(15 23 42);
        color: white;
        font-size: 0.8125rem;
        font-weight: 600;
        line-height: 1;
        padding: 0.5rem 0.875rem;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-btn:hover {
        background: rgb(30 41 59);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-row {
        display: flex;
        align-items: center;
        gap: 0.875rem;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.5rem;
        background: rgb(249 250 251);
        padding: 0.875rem 1rem;
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-row {
        border-color: rgb(55 65 81);
        background: rgb(31 41 55);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-index {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 2rem;
        height: 2rem;
        border-radius: 0.375rem;
        background: rgb(229 231 235);
        color: rgb(55 65 81);
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-index-active {
        background: rgb(15 23 42);
        color: white;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-body {
        flex: 1;
        min-width: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-link {
        display: block;
        color: inherit;
        text-decoration: none;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-link:hover .csn-segment-title {
        color: rgb(13 148 136);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: rgb(17 24 39);
        line-height: 1.3;
        transition: color 0.15s ease;
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-title {
        color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-meta {
        margin-top: 0.125rem;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
        line-height: 1.35;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-route {
        flex-shrink: 0;
        max-width: 40%;
        border-radius: 9999px;
        background: rgb(243 244 246);
        padding: 0.375rem 0.75rem;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        color: rgb(55 65 81);
        line-height: 1.35;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dark .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-route {
        background: rgb(17 24 39);
        color: rgb(209 213 219);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-delete {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 1.75rem;
        height: 1.75rem;
        border: none;
        border-radius: 9999px;
        background: transparent;
        color: rgb(239 68 68);
        padding: 0;
        cursor: not-allowed;
        opacity: 0.85;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-delete svg {
        width: 1.375rem;
        height: 1.375rem;
    }

    @media (max-width: 767px) {
        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-row {
            flex-wrap: wrap;
            align-items: flex-start;
        }

        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-route {
            max-width: 100%;
            margin-left: 2.875rem;
        }

        .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-segment-delete {
            margin-left: auto;
        }
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-mini-card {
        border: 1px solid rgb(229 231 235);
        border-radius: 0.375rem;
        padding: 0.5rem 0.625rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-empty-state {
        border: 1px dashed rgb(229 231 235);
        border-radius: 0.375rem;
        padding: 1.5rem;
        text-align: center;
        font-size: 0.8125rem;
        color: rgb(107 114 128);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-table {
        width: 100%;
        font-size: 0.75rem;
        border-collapse: collapse;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-table th {
        background: rgb(249 250 251);
        padding: 0.375rem 0.5rem;
        text-align: left;
        font-weight: 600;
        color: rgb(75 85 99);
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .csn-view .csn-table td {
        border-top: 1px solid rgb(243 244 246);
        padding: 0.375rem 0.5rem;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .fi-in-entry-wrp-label {
        display: none;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .fi-in-section-content {
        padding: 0;
    }

    .fi-resource-consignment-notes.fi-resource-view-record-page .fi-in-section,
    .fi-resource-consignment-notes.fi-resource-view-record-page .fi-in-entry-wrp {
        max-width: none !important;
    }

    /* Consignment Notes list — search & filter toolbar */
    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-ctn {
        background: transparent !important;
        overflow: visible !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        --tw-ring-shadow: 0 0 #0000 !important;
        --tw-ring-offset-shadow: 0 0 #0000 !important;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-ctn {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        margin-bottom: 0.75rem;
        overflow: hidden;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-ctn.divide-y > :not([hidden]) ~ :not([hidden]) {
        border-top-width: 0;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-toolbar {
        display: flex;
        align-items: center;
        padding: 0.875rem 1rem !important;
        gap: 1rem;
        min-height: 3.25rem;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-toolbar > .flex.shrink-0:empty,
    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-toolbar > .flex.shrink-0:not(:has(*:not([x-cloak]))) {
        display: none;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto {
        margin-left: 0 !important;
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-search-field {
        flex: 0 1 20rem;
        width: 100%;
        max-width: 20rem;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-search-field .fi-input-wrp {
        border-radius: 0.375rem;
        background: white;
        box-shadow: none;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-search-field input {
        font-size: 0.875rem;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-search-field input::placeholder {
        color: rgb(156 163 175);
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-filters-dropdown,
    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-column-toggle-dropdown {
        flex-shrink: 0;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto > div:last-child {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: auto;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-filters-dropdown .fi-icon-btn,
    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-column-toggle-dropdown .fi-icon-btn {
        color: rgb(107 114 128);
        position: relative;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-filters-dropdown .fi-icon-btn:hover,
    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-column-toggle-dropdown .fi-icon-btn:hover {
        color: rgb(55 65 81);
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-filters-dropdown .fi-badge {
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

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-filter-indicators {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        margin-bottom: 0.75rem;
        padding: 0.625rem 1rem;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-content {
        background: white;
        border: 1px solid rgb(229 231 235);
        border-radius: 0.75rem;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04);
        overflow: hidden;
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-content.divide-y > :not([hidden]) ~ :not([hidden]) {
        border-color: rgb(243 244 246);
    }

    .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-cell {
        font-size: 0.6875rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: rgb(107 114 128);
    }

    @media (max-width: 767px) {
        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto {
            flex-direction: column;
            align-items: stretch;
        }

        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-search-field {
            max-width: none;
        }

        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-filters-dropdown {
            margin-left: 0;
            align-self: flex-end;
        }

        .fi-resource-consignment-notes.fi-resource-list-records-page .fi-ta-header-toolbar > .ms-auto > div:last-child {
            justify-content: flex-end;
        }
    }
</style>

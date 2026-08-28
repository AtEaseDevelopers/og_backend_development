<style>
    @if(! empty($forPdf))
        @page { margin: 22px 26px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.35;
        }
    @endif

    .csn-document {
        font-family: Arial, Helvetica, DejaVu Sans, sans-serif;
        font-size: 10px;
        color: #000;
        line-height: 1.4;
    }

    .csn-document table {
        width: 100%;
        border-collapse: collapse;
    }

    .csn-doc-header td {
        vertical-align: top;
        padding: 0 0 8px;
    }

    .csn-doc-logo-cell {
        width: 28%;
    }

    .csn-doc-logo {
        max-height: 58px;
        max-width: 150px;
    }

    .csn-doc-company-cell {
        text-align: right;
        font-size: 9px;
    }

    .csn-doc-company-name {
        font-size: 11px;
        font-weight: bold;
        margin-bottom: 2px;
    }

    .csn-doc-parties td {
        vertical-align: top;
        padding: 0 0 8px;
    }

    .csn-doc-party-col {
        width: 58%;
        padding-right: 12px;
    }

    .csn-doc-meta-col {
        width: 42%;
        text-align: right;
    }

    .csn-doc-title {
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 6px;
    }

    .csn-doc-meta-table {
        width: auto;
        margin-left: auto;
        font-size: 10px;
    }

    .csn-doc-meta-table td {
        padding: 1px 0 1px 10px;
        vertical-align: top;
    }

    .csn-doc-meta-label {
        font-weight: bold;
        padding-right: 8px;
        white-space: nowrap;
    }

    .csn-doc-label {
        font-weight: bold;
    }

    .csn-doc-label-spaced {
        margin-top: 10px;
    }

    .csn-doc-party-name {
        margin-top: 2px;
    }

    .csn-doc-party-address {
        margin-top: 2px;
        white-space: pre-line;
    }

    .csn-doc-route {
        margin: 0 0 8px;
        font-size: 10px;
    }

    .csn-doc-items th,
    .csn-doc-items td {
        border: 1px solid #000;
        padding: 5px 6px;
        vertical-align: top;
    }

    .csn-doc-items th {
        font-weight: bold;
        text-align: center;
        font-size: 9px;
    }

    .csn-doc-qty {
        width: 12%;
    }

    .csn-doc-desc {
        width: 38%;
    }

    .csn-document--no-pricing .csn-doc-desc,
    .csn-document--no-pricing .csn-doc-items .csn-doc-desc {
        width: auto;
    }

    .csn-doc-amt {
        width: 12.5%;
        text-align: center;
    }

    .csn-doc-amt-value {
        text-align: right;
    }

    .csn-doc-line-item {
        margin-bottom: 4px;
    }

    .csn-doc-dropoff {
        margin-top: 14px;
        padding-top: 8px;
        border-top: 1px solid #000;
    }

    .csn-doc-dropoff-title {
        font-weight: bold;
        margin-bottom: 4px;
    }

    .csn-doc-total-row td {
        border: 1px solid #000;
        padding: 5px 6px;
    }

    .csn-doc-total-label {
        text-align: right;
        font-weight: bold;
    }

    .csn-doc-muted {
        color: #666;
        font-style: italic;
    }

    .csn-doc-footer {
        margin-top: 10px;
    }

    .csn-doc-footer td {
        border: 1px solid #000;
        vertical-align: top;
        padding: 8px;
        height: 110px;
    }

    .csn-doc-footer-left {
        width: 58%;
    }

    .csn-doc-footer-right {
        width: 42%;
        text-align: center;
    }

    .csn-doc-footer-field {
        margin-bottom: 8px;
        min-height: 16px;
    }

    .csn-doc-sign-line {
        border-bottom: 1px dotted #000;
        height: 28px;
        margin: 0 12px 6px;
    }

    .csn-doc-sign-note {
        font-size: 9px;
        margin-top: 8px;
    }

    .csn-doc-sign-date {
        font-size: 9px;
        margin-top: 10px;
        text-align: left;
        padding-left: 12px;
    }

    .csn-document--screen {
        background: #fff;
        border: 1px solid #d6d3d1;
        border-radius: 6px;
        padding: 16px;
    }
</style>

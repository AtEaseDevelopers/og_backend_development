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

    .inv-document {
        font-family: Arial, Helvetica, DejaVu Sans, sans-serif;
        font-size: 10px;
        color: #000;
        line-height: 1.4;
    }

    .inv-document table {
        width: 100%;
        border-collapse: collapse;
    }

    .inv-header td {
        vertical-align: top;
        padding: 0 0 10px;
    }

    .inv-logo-cell {
        width: 28%;
    }

    .inv-logo {
        max-height: 58px;
        max-width: 150px;
    }

    .inv-company-cell {
        text-align: right;
        font-size: 9px;
    }

    .inv-company-name {
        font-size: 11px;
        font-weight: bold;
        margin-bottom: 2px;
    }

    .inv-title-row td {
        vertical-align: top;
        padding: 0 0 8px;
    }

    .inv-bill-to {
        width: 58%;
        padding-right: 12px;
    }

    .inv-bill-label {
        font-weight: bold;
        margin-bottom: 4px;
    }

    .inv-bill-name {
        font-weight: bold;
        margin-bottom: 2px;
    }

    .inv-bill-address {
        white-space: pre-line;
    }

    .inv-title-cell {
        width: 42%;
        text-align: right;
    }

    .inv-title {
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }

    .inv-number {
        margin-top: 4px;
        font-size: 10px;
    }

    .inv-meta-row td {
        padding: 6px 0 10px;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        font-size: 10px;
    }

    .inv-meta-left {
        width: 34%;
    }

    .inv-meta-center {
        width: 32%;
        text-align: center;
    }

    .inv-meta-right {
        width: 34%;
        text-align: right;
    }

    .inv-items th,
    .inv-items td {
        border-bottom: 1px solid #000;
        padding: 6px 5px;
        vertical-align: top;
        font-size: 9px;
    }

    .inv-items th {
        font-weight: bold;
        text-align: center;
        border-top: 1px solid #000;
    }

    .inv-col-date { width: 9%; text-align: center; }
    .inv-col-csn { width: 14%; text-align: center; }
    .inv-col-ref { width: 22%; }
    .inv-col-dest { width: 9%; text-align: center; }
    .inv-col-sst { width: 10%; text-align: right; }
    .inv-col-amt { width: 12%; text-align: right; }

    .inv-ref-line {
        margin-bottom: 2px;
    }

    .inv-totals-wrap {
        margin-top: 8px;
    }

    .inv-totals {
        width: 42%;
        margin-left: auto;
        border-collapse: collapse;
    }

    .inv-totals td {
        padding: 4px 6px;
        font-size: 10px;
    }

    .inv-totals-label {
        text-align: left;
    }

    .inv-totals-value {
        text-align: right;
        white-space: nowrap;
        min-width: 5.5rem;
    }

    .inv-totals-divider td {
        border-top: 1px solid #000;
        padding-top: 6px;
    }

    .inv-totals-grand td {
        font-weight: bold;
        border-top: 2px solid #000;
        border-bottom: 3px double #000;
        padding-top: 6px;
        padding-bottom: 6px;
    }

    .inv-words {
        margin-top: 14px;
        font-weight: bold;
        font-size: 10px;
        text-transform: uppercase;
    }

    .inv-disclaimer {
        margin-top: 10px;
        font-size: 8px;
        line-height: 1.45;
        text-align: justify;
    }

    .inv-banks {
        margin-top: 12px;
        font-size: 9px;
    }

    .inv-banks td {
        width: 50%;
        vertical-align: top;
        padding-right: 8px;
    }
</style>

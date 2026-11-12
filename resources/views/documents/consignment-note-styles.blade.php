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

    /* one font on every document, the order form's (client, 5 Nov 2026); larger type */
    .csn-document {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10.5px;
        color: #000;
        line-height: 1.4;
    }

    .csn-document table {
        width: 100%;
        border-collapse: collapse;
    }

    /* the letterhead as the order form's (client, 6 Nov 2026) */
    .csn-document .lh-name { font-size: 17px; font-weight: bold; }
    .csn-document .lh-small { font-size: 10px; line-height: 1.35; }
    .csn-document .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
    .csn-document .lh-meta { width: 100%; border-collapse: collapse; }
    .csn-document .lh-meta td { padding: 0; vertical-align: bottom; }
    .csn-document .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
    .csn-document .lh-qr { width: 190px; text-align: right; vertical-align: top; padding-top: 4px; }
    .csn-document .lh-qr img { width: 180px; height: 40px; }
    .csn-document .lh-qr-text { font-size: 14px; font-weight: bold; letter-spacing: .8px; margin-top: 2px; }
    .csn-doc-parties { margin-top: 8px; }

    /* easier to read when printed (client, 6 Nov 2026): the items, the route and the consignee larger */
    /* the items in the order quotation's font and cells, as every document (client, 13 Nov 2026) */
    .csn-doc-items td { font-size: 10.5px; }
    .csn-doc-consignee-name { font-size: 13px; font-weight: bold; }
    .csn-doc-consignee-box .csn-doc-party-address { font-size: 11.5px; }

    /* the chop & sign fields: Name, IC No. and Date on their own lines with room to write */
    .csn-doc-sign-field { text-align: left; font-size: 10.5px; margin-top: 7px; padding-left: 10px; }
    .csn-doc-sign-blank { display: inline-block; width: 62%; border-bottom: 1px solid #000; height: 11px; vertical-align: bottom; }

    /* the "side" layout: the consignor at the left, the consignee beside in a tall box */
    .csn-doc-side .csn-doc-side-left { width: 44%; }
    .csn-doc-side .csn-doc-side-right { width: 56%; min-height: 200px; height: 200px; }
    .csn-doc-side { margin-bottom: 8px; }
    .csn-doc-side-party + .csn-doc-side-party { margin-top: 10px; padding-top: 8px; border-top: 1px dotted #999; }

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

    /* QR of the document number, top right beside the title and meta */
    .csn-doc-qr {
        float: right;
        margin-left: 10px;
        text-align: center;
    }

    .csn-doc-qr img {
        width: 80px;
        height: 80px;
        display: block;
    }

    .csn-doc-qr-text {
        font-size: 14px;
        font-weight: bold;
        letter-spacing: .8px;
        margin-top: 2px;
    }

    .csn-doc-title {
        font-size: 14px;
        font-weight: bold;
        margin-bottom: 6px;
    }

    .csn-doc-meta-table {
        width: auto;
        margin-left: auto;
        font-size: 10.5px;
    }

    .csn-doc-meta-table td {
        padding: 1px 0 1px 10px;
        vertical-align: top;
    }

    /* labels bold, values in normal text (user, 6 Nov 2026) */
    .csn-doc-meta-label {
        font-weight: bold;
        font-size: 9.5px;
        text-transform: uppercase;
        padding-right: 8px;
        white-space: nowrap;
    }
    .csn-doc-meta-table td + td {
        font-weight: normal;
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

    /* the route in large type (client, 30 Oct 2026: "From KL to Johor" was hard to see) */
    .csn-doc-route {
        margin: 6px 0;
        padding: 4px 8px;
        font-size: 15px;
        font-weight: bold;
        border-top: 2px solid #000;
        border-bottom: 2px solid #000;
        text-transform: uppercase;
    }
    .csn-doc-route-label { font-weight: normal; font-size: 10px; text-transform: none; }
    .csn-doc-route-arrow { padding: 0 10px; }

    .csn-doc-items th,
    .csn-doc-items td {
        border: 1px solid #000;
        padding: 4px 6px;
        vertical-align: top;
    }

    .csn-doc-items th {
        font-weight: bold;
        text-align: left;
        font-size: 10px;
        text-transform: uppercase;
        background: #f0f0f0;
    }
    .csn-doc-no { width: 5%; text-align: center; }
    .csn-doc-uom { width: 12%; text-align: center; }
    /* the headings over their columns as the order quotation's: No. and UOM centred, Qty at the right */
    .csn-doc-items th.csn-doc-no, .csn-doc-items th.csn-doc-uom { text-align: center; }
    .csn-doc-items th.csn-doc-qty { text-align: right; }

    .csn-doc-qty {
        width: 9%;
        text-align: right;
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
        font-size: 10px;
        margin-top: 8px;
    }

    .csn-doc-sign-date {
        font-size: 10px;
        margin-top: 10px;
        text-align: left;
        padding-left: 12px;
    }

    /* the client's layout (user, 30 Oct 2026): letterhead left, title + QR right; Bill To and Consignor boxes with
       the numbers beside them; a chop & sign box per consignee at the bottom */
    .csn-doc-company-cell-left {
        text-align: left;
        font-size: 10.5px;
        width: 58%;
    }
    .csn-doc-company-cell-left .csn-doc-company-name {
        font-size: 15px;
    }
    .csn-doc-title-cell {
        text-align: right;
        vertical-align: top;
    }
    .csn-doc-title-cell .csn-doc-title {
        font-size: 16px;
        margin-bottom: 4px;
    }
    .csn-doc-qr-title img {
        width: 180px;
        height: 40px;
        display: inline-block;
    }
    .csn-doc-qr-text {
        font-size: 14px;
        font-weight: bold;
        letter-spacing: .8px;
    }
    .csn-doc-parties .csn-doc-party-col {
        width: 58%;
    }
    .csn-doc-parties .csn-doc-meta-col {
        width: 42%;
        padding-left: 10px;
    }
    .csn-doc-consignors {
        margin-top: 0;
    }
    .csn-doc-consignor-box {
        min-height: 0;
    }
    .csn-doc-pickups {
        width: 100%;
        border-collapse: collapse;
        margin-top: 3px;
    }
    .csn-doc-pickups td {
        padding: 2px 0;
        vertical-align: top;
        border-top: 1px dotted #999;
    }
    .csn-doc-pickup-no {
        width: 60px;
        font-weight: bold;
        white-space: nowrap;
        padding-right: 6px !important;
    }
    .csn-doc-pickup-do {
        white-space: nowrap;
        padding-left: 6px;
    }
    .csn-doc-parties .csn-doc-qr {
        float: none;
        margin: 0 0 4px auto;
        text-align: right;
    }
    .csn-doc-parties .csn-doc-qr img {
        width: 56px;
        height: 56px;
        display: inline-block;
    }
    /* tighter boxes (13 Nov 2026): every address takes three lines now, and a CSN with five items still fits a page */
    .csn-doc-remark {
        margin-top: 6px;
    }
    .csn-doc-remark td {
        border: 1px solid #000;
        padding: 5px 8px;
        min-height: 40px;
        vertical-align: top;
    }
    .csn-doc-consignees {
        margin-top: 6px;
    }
    .csn-doc-consignee-box {
        width: 50%;
        border: 1px solid #000;
        padding: 6px 8px;
        vertical-align: top;
    }
    .csn-doc-sign-area {
        margin-top: 4px;
        text-align: center;
    }
    .csn-doc-sign-area .csn-doc-sign-line {
        height: 22px;
        margin: 0 10px 4px;
    }
    .csn-doc-sign-area .csn-doc-sign-date {
        text-align: center;
        padding-left: 0;
        margin-top: 6px;
    }
    .csn-document--screen {
        background: #fff;
        border: 1px solid #d6d3d1;
        border-radius: 6px;
        padding: 16px;
    }
</style>

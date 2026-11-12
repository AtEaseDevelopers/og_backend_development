<style>
    @if(! empty($forPdf))
        @page { margin: 26px 28px 40px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #000;
            line-height: 1.35;
        }
    @endif

    /* the invoice / cash bill after the client's AutoCount sample (6 Nov 2026): the order form's letterhead and
       billing block, one bordered table of consignment notes, the totals, the notes with the e-Invoice QR, the terms */
    .inv-document {
        font-family: DejaVu Sans, sans-serif;
        font-size: 10.5px;
        color: #000;
        line-height: 1.4;
    }

    .inv-document table {
        width: 100%;
        border-collapse: collapse;
    }

    /* the letterhead as the order form's */
    .inv-document .lh-name { font-size: 17px; font-weight: bold; }
    .inv-document .lh-small { font-size: 10px; line-height: 1.35; }
    .inv-document .lh-rule { border-top: 4px solid #000; margin: 5px 0 6px; }
    .inv-document .lh-meta { width: 100%; border-collapse: collapse; }
    .inv-document .lh-meta td { padding: 0; vertical-align: bottom; }
    .inv-document .lh-title { font-size: 19px; font-weight: bold; text-align: right; }
    .inv-document .lh-qr { width: 190px; text-align: right; vertical-align: top; padding-top: 4px; }
    .inv-document .lh-qr img { width: 180px; height: 40px; }
    .inv-document .lh-qr-text { font-size: 14px; font-weight: bold; letter-spacing: .8px; margin-top: 2px; }

    /* the billing customer and the details */
    .inv-parties { margin: 8px 0 10px; }
    .inv-parties td { vertical-align: top; padding: 0; }
    .inv-party-col { width: 58%; padding-right: 12px; }
    .inv-parties td.inv-meta-col { width: 42%; padding-left: 10px; }
    .inv-meta-table { width: auto; }
    .inv-meta-table td { padding: 1px 0; vertical-align: top; }
    .inv-meta-k { width: 7rem; font-weight: bold; font-size: 9.5px; text-transform: uppercase; white-space: nowrap; }
    .inv-meta-v { font-weight: normal; }

    /* the consignment notes: the order quotation's items table (pdf.partials.common), the same font and cells on
       every document (client, 13 Nov 2026) */
    .inv-items { margin: 0 0 8px; }
    .inv-col-no { width: 4%; text-align: center; }
    .inv-col-date { width: 10%; white-space: nowrap; }
    .inv-col-csn { width: 13%; white-space: nowrap; }
    .inv-col-ref { width: 10%; }
    .inv-col-area { width: 10%; }
    .inv-col-consignee { width: 14%; }
    .inv-col-goods { width: auto; }
    .inv-col-amt { width: 12%; text-align: right; white-space: nowrap; }
    .inv-ref-line { margin-bottom: 2px; }

    /* the amount in words beside the totals */
    .inv-foot { margin-top: 8px; }
    .inv-foot td { vertical-align: top; padding: 0; }
    .inv-words-cell { width: 55%; padding-right: 12px; }
    .inv-words { font-weight: bold; font-size: 10.5px; text-transform: uppercase; padding-top: 4px; }
    .inv-totals-cell { width: 45%; }
    /* as the sample (user, 6 Nov 2026): only the amount has a border, the labels plain at its left */
    .inv-totals-boxed { border-collapse: separate; border-spacing: 0 4px; }
    .inv-totals-boxed td { padding: 3px 6px; font-size: 10.5px; }
    .inv-totals-label { text-align: right; font-weight: bold; border: 0; }
    .inv-totals-value { text-align: right; white-space: nowrap; width: 34%; border: 1px solid #000; font-weight: bold; }
    .inv-totals-boxed .inv-totals-grand td.inv-totals-value { font-size: 11.5px; border-width: 2px; }

    /* the notes and the e-Invoice QR */
    .inv-notes-row { margin-top: 12px; border-top: 1px solid #000; padding-top: 6px; }
    .inv-notes-row td { vertical-align: top; padding: 6px 0 0; }
    .inv-notes { font-size: 9.5px; line-height: 1.45; }
    .inv-notes-title { font-weight: bold; }
    .inv-generated { margin-top: 8px; font-size: 9.5px; font-weight: bold; }
    .inv-einv { width: 120px; text-align: center; font-size: 9px; }
    .inv-einv-qr { width: 90px; height: 90px; }
    .inv-einv-box { width: 88px; height: 88px; border: 1px dashed #999; margin: 0 auto; }
    .inv-einv-label { margin-top: 3px; }

    /* the terms & conditions, in the sample's small type */
    .inv-terms { margin-top: 10px; font-size: 7.5px; line-height: 1.4; text-align: justify; page-break-inside: avoid; }
    .inv-terms-title { font-size: 10px; font-weight: bold; margin-bottom: 2px; }
    .inv-terms div { margin-bottom: 1px; }
</style>

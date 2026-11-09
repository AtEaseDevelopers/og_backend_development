{{-- shared by every PDF (client, 5 Nov 2026): one font (the order form's), the billing block, the order form's items
     table; the page number is stamped by App\Support\PdfPageNumbers --}}
<style>
    body { font-family: DejaVu Sans, sans-serif; color: #000; }
    /* the document number under its barcode: large and readable; printed nowhere else */
    .lh-qr { width: 190px; text-align: right; vertical-align: top; padding-top: 4px; }
    .lh-qr img { width: 180px; height: 40px; }
    .lh-qr-text { font-size: 14px; font-weight: bold; letter-spacing: .8px; margin-top: 2px; }
    /* the billing customer block: a bold label, the value in normal text (user, 6 Nov 2026) */
    table.bt { border-collapse: collapse; width: 100%; }
    table.bt th { text-align: left; font-size: 10.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .02em; border-bottom: 1px solid #000; padding: 0 0 3px; }
    table.bt td { padding: 1px 6px 1px 0; vertical-align: top; }
    table.bt td.bt-k { width: 5.6rem; white-space: nowrap; font-weight: bold; font-size: 9.5px; text-transform: uppercase; }
    table.bt td.bt-v { font-weight: normal; }
    /* the heading row over both columns (billing left, details right): one line across the page (client, 7 Nov 2026) */
    th.bt-heading { text-align: left; font-size: 10.5px; font-weight: bold; text-transform: uppercase; letter-spacing: .02em; border-bottom: 1px solid #000; padding: 0 0 3px; }
    tr.bt-body > td { padding-top: 3px !important; }
    /* the order form's items table */
    table.items { width: 100%; border-collapse: collapse; margin: 0 0 8px; }
    table.items th, table.items td { border: 1px solid #000; padding: 4px 6px; vertical-align: top; }
    table.items th { background: #f0f0f0; font-size: 10px; text-align: left; text-transform: uppercase; }
    table.items td.num, table.items th.num { text-align: right; white-space: nowrap; }
    table.items td.c, table.items th.c { text-align: center; white-space: nowrap; }
    table.items tr.total td { font-weight: bold; background: #f7f7f7; }
    table.items td.blank { border-left: 0; border-bottom: 0; }
    /* the totals rows (client, 7 Nov 2026): only the amount has a border, the label plain beside it, as the invoice */
    table.items tr.tot td { border: 0; background: none; padding-top: 3px; padding-bottom: 3px; }
    table.items tr.tot td.amt { border: 1px solid #000; font-weight: bold; }
    table.items tr.tot.total td.amt { border-width: 2px; font-size: 11.5px; }
    table.items tr.tot td.lbl { font-weight: bold; }
    table.items .muted { color: #444; font-weight: normal; }
</style>

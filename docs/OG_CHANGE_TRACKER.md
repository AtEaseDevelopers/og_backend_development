# O&G Change Tracker (handoff file)

> **For any Claude session (any account):** read this file first. It records the user's feedback rounds,
> what is done, what is left, and the rules the user has set. Update it whenever an item changes status,
> and refresh "Last updated" with the output of plain `date` (local Malaysia time).

**Order page round: finished (user confirmed 8 Oct 2026). Next feedback is for other pages.**

**Last updated:** Thu 8 Oct 2026, 9:28 AM (Malaysia time)

## How the user works

- The user sends feedback **one item at a time**, with screenshots, in casual Malaysian English ("jiu" = then).
  Do **not** revive the old 20-item plan; only work on items the user sends.
- **Never commit or push unless the user asks.** Everything since commit `586302d` is uncommitted (~90 files).
- Positive/confirm buttons go on the **right**.
- Keep light/dark mode and phone width working.
- Usage-conscious: do small changes directly; avoid big multi-agent workflows (at most one builder + one reviewer).
- The live server is `ogtransport.at-eases.com`. The user runs server commands themselves.

## Environment notes (Windows, Laragon)

- Run PHP only via: `C:/laragon/bin/php/php-8.3.3-Win32-vs16-x64/php.exe -d display_startup_errors=0`
  (lint with `-l`, then `artisan view:clear` and `artisan optimize:clear`; `artisan test` has 1 expected failure in `tests/Feature/ExampleTest.php`).
- Local site: `http://og_backend_development.test/admin` (tenant `KL`). Local seed login: see the database seeders.
- Files use CRLF: `sed` with `$` anchors fails; use the Edit tool or a small Python script.
- **Never put a Blade component tag (`<x-...>`) inside a CSS or HTML comment** in a `.blade.php` file (it broke every page once).
- Livewire testing in the browser: `Livewire.all().find(c => c.name.includes('create-order'))`. Globals `window.OgDateRange` and `window.OgSearchableSelect` exist.

## Business rules the user confirmed

- **Pricing precedence:** customer special price, then UOM price list, then manual price. Never drop a product line just because it has no price (keep it unpriced). Pricing is "not finalised", so expect changes.
- **Table filters:** "every column filterable" means a filter card above the table. Column headers only **sort**; no per-column filter inputs.
- **Store** (Consignor Pickup/Store toggle) = an O&G branch, with its address pre-filled.
- **PIC name + contact number** on both consignor and consignee.
- **Blank consignor stays blank** (no "Blank = customer" placeholder, no silent fallback to the customer name).
- **Payment term by customer type:** Credit customer: Credit / Cash / COD; COD customer: COD only; Cash customer: Cash only.

## Server steps the user must run after deploying

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate
```

`composer install` is needed because `chillerlan/php-qrcode` (QR codes on CSN / DO) was added on 8 Oct 2026; it needs the PHP `gd` extension.

New migrations (already run locally; 3 so far):
- `2026_10_08_100000_add_consignor_mode_and_pic_to_quotations.php` (consignor/consignee PIC name/phone, store_branch_id)
- `2026_10_08_120000_make_quotation_line_unit_price_nullable.php` (NULL price = "no price yet")
- `2026_10_08_130000_add_multiple_payment_attachments.php` (payment_submissions.receipt_paths, payments.slip_paths JSON lists)

## Status

Legend: [x] done · [~] done in code, not yet browser-checked · [ ] not started · [?] needs the user

### Orders list and general
- [x] Branch chooser page removed; "Change branch" removed from the profile menu.
- [x] Orders: extra text and Order intake card removed.
- [x] Orders: stage tags with counts (All + one per stage; click filters). Stage dropdown removed.
- [x] Orders: customer type filter (Cash / COD / Credit) and a badge beside each customer name.
- [x] Orders: date range pickers beside the search.
- [x] Orders: Service filter (All / Pick Up / Store).
- [x] Orders: wider Order / Customer column; sortable headers, no per-column filters.
- [x] Searchable (select2-style) dropdowns across the system, including the customer portal.
- [x] Date range picker on every date filter (Reports hub, Lorry Schedule single day, Credit approval, COD listing, Delivery Monitoring, Failed deliveries, Missing CSN logs, Portal enquiries, Vehicle maintenance).
  Component: `<x-og.date-range from=".." to=".." :from-value=".." :to-value=".." label=".." />`; Filament: `App\Filament\Forms\Components\DateRangePicker`, `App\Filament\Tables\Filters\DateRangeFilter`.

### CSN list and Job Sheet
- [x] CSN page: Orders-style filter card, CSN / Job / Created date ranges, AirAsia-style day strip, description removed.
- [x] CSN date defaults to today (`?csn=all` shows all dates); column toggle at the end of the header row; all columns sortable.
- [x] Job Sheet: every column sortable, operating date range, Trip and Task Count filters.
- [ ] Full browser pass of tables/date pickers in light, dark and phone width (not done yet).

### Create / Edit order
- [x] One billing address beside Customer (pre-filled, editable).
- [x] Received through optional; shown in the order overview.
- [x] Payment term follows the customer type; Service and Payment method fields removed.
- [x] Consignor Pickup / Store toggle; consignor defaults to the customer with a Clear link.
- [x] PIC name + contact on both sides; Pickup location detail removed; names optional; drop-off type in the consignee block.
- [x] DO number and Expected delivery date required (Expected delivery date becomes the CSN date).
- [x] Drop-off location detail removed (Drop-off location works like Pickup location: saved address or "+ New address").
- [x] Photos upload inside each consignor & consignee section, above Instructions (browser-checked).
- [x] Purchase history icon per product row: opens the customer's previous prices for that product, same destination first, scoped to the current company (browser-checked).
- [x] Blank consignor stays blank (placeholders gone; CSN keeps it blank, also after editing the CSN).
- [x] Products without a price still show in Items & pricing (bug: "Air Compressor 5 HP" on KL-ORD-202610-0008 / record 58 now shows with "No price yet"). Send / Customer confirmed is blocked while any product has no price.

### Order details page
- [x] Overview: narrower right column, in order Record ownership, Payment summary, Linked records.
- [x] "Payment & release" renamed Payment summary, with payment history inside the card; Admin release card removed (the release button stays inside Payment summary only when a release applies).
- [x] Tabs: Overview, Items & pricing, Activity. Old `?tab=payment` / `?tab=documents` links land on Overview.
- [x] Add / Edit payment at any stage (reason required, activity logged; paid / outstanding recalculated). Two-user approval for Cash / Pay-at-Counter is respected; COD orders cannot get an upfront payment while the driver still has to collect.
- [x] Items & pricing moved into the left column, right under Customer & order (order and enquiry overview). Overview columns are 70% left / 30% right (stacked below 1100px). In Items & pricing, "Before proceeding" sits above "Admin pricing" (full width, details in one row).
- [x] "Customer accepted the quotation" modal: new channel **Customer portal**; "Confirmed by" and "Evidence / remarks" are optional (blank confirmed-by saves the customer company name).
- [x] "Customer rejected" with "Return the order to editing" now creates the **next version** (e.g. V2 rejected -> V3 draft for re-pricing); the rejected version is kept as Superseded.
- [x] Earlier versions are listed in Linked records ("Earlier version N", opens that version + its PDF), and the Activity tab includes their history prefixed "V1 · ...".
- [x] "Superseded" renamed **"Old version"** everywhere (grey pill). Its banner is a blue info box: "Old version · replaced by version N", the reason, and an "Open latest version →" link. Activity log says "Replaced by version N".
- [x] Old versions are **view only**: no Assign salesperson, Edit order, pricing inputs, payment or any action buttons; actions are also refused on the server ("This is an old version · view only").
- [x] Activity log: "Quotation sent / accepted · price offered" entries show a short title ("Quotation v2 sent to customer via Email, WhatsApp") plus a small table (Product · destination, Qty, Unit price, Amount, Price offered). Old "Superseded by version N" logs read "Replaced by version N".
- [x] Quantity no longer prints the internal UOM product code (e.g. `1 EXTRA_LONG_CARTON_40KG...` -> `1`); packaging units stay (`12 CTN`). Shared helper `App\Support\QuantityLabel`, used on the proforma PDF, order overview, Orders list, portal enquiries and purchase history. Proforma PDF columns: Item / service 52%, Route 15%, Qty 6%, Unit 11%, Amount 12% (number headers right-aligned).
- [x] Add / Edit payment accepts **up to 10 slips / receipts** (images or PDF, 8 MB each). The first file stays in receipt_path / slip_path for the portal and other screens; all files show in Payment history. Edit can add or remove files (logged as "attachments 3 -> 2 file(s)").
- [x] Payment slip upload shows small square thumbnails (6 per row, 3 on phones). Clicking a slip in Payment history opens it in a pop-up viewer (image or PDF) with "Open in new tab"; Esc or clicking outside closes it.
- [x] Clicking a tile in the payment upload field also opens the slip in the pop-up viewer (Esc closes only the viewer, not the payment window).
- [x] Overview layout now: left = Customer & order -> Items & pricing -> **Payment summary**; right = Record ownership -> Linked records.
- [x] Payment history is **collapsed by default**; click the heading ("N entries · Show") to expand / hide. A "N to review" pill shows on the heading when a payment waits for verify / approve.
- [x] **Email document** (Linked records button, was "Email invoice / cash bill"): wide window; left = pick the document (any quotation version, proforma, invoice / cash bill, CSN, DO), To, Subject, Message (suggested text per document, editable like Gmail); right = live PDF preview + "Open in new tab". Activity records "<document> emailed to <address> (status) · Subject: ...". Code: `App\Domains\Quotation\Actions\SendOrderDocument`.
- [x] Linked records shows at most 10 rows; more scroll inside the card. The heading shows the count.
- [x] **System Settings -> Email (SMTP)**: choose "Not set up (log only)" or SMTP; host, port, encryption (TLS/SSL/None), username, password (stored encrypted, never shown back), from address / name; header button **Send test email**. Every system email uses it (`App\Support\MailSettings::apply()`); until set up, emails are only written to the log (local .env MAIL_MAILER=log), even though they show "sent".
- [x] **COD / Term billing rules** (user confirmed 8 Oct 2026):
  - On confirmation COD and Term orders get the **CSN only, no invoice** (`GenerateOrderBilling`). Cash unchanged (Cash Bill per payment, then CSN).
  - **COD:** Admin can add / edit / upload payments in Payment summary. The **COD invoice is issued automatically once fully paid** (`RefreshOrderPaidAmount` -> `GenerateOrderBilling::issueInvoice`). The driver only collects what is still outstanding on delivery (`CompleteDelivery::codDue`).
  - **Term:** no Add payment in Payment summary (paid against the invoice in Invoices / AR). **"Generate invoice"** button in Linked records any time after the CSN exists (the confirm window says whether the CSN is returned). One invoice per order; a CSN already on a monthly consolidated invoice is refused.
  - Monthly "Consolidate term invoices" no longer re-invoices a CSN invoiced in another month.
  - Existing orders keep the invoices they already have.
- [x] Payment history shows uploaded slips (thumbnail or "Open PDF").
- [?] Editing a payment does **not** change documents already issued (Receipt, Cash Bill, Refund Note); the edit window warns about it. Ask the user if those should update automatically.

### COD Reconciliation page (Billing -> COD Reconciliation)
- [x] Rebuilt as a list of every COD payment, filtered by **payment date (default today)**, Status (Pending verification / Verified / Approved by admin), Driver, and a Search box in the same filter row.
- [x] Driver collections (recorded on delivery) show **Pending verification**; Admin clicks **Verify** and can enter the actual amount received (shortage recorded on the payment + payment voucher for the driver). Bulk "Verify selected" verifies at the recorded amounts.
- [x] Payments Admin records on a COD order (Order details -> Payment summary) also appear, as **Approved by admin**.
- [x] The **COD invoice is only issued once the order is fully paid AND every driver collection is verified** (`VerifyCodCollection`, `RefreshOrderPaidAmount`).
- [x] Columns: Date, Order / CSN (link), Customer, Recorded by (Driver / Admin + method), Expected, Amount (with total), Status (+ verified by), Invoice.

### CSN page (round 2)
- [x] Checkbox on every CSN row. Bulk **Assign to lorry** (main lorry, driver, date, optional extra lorries) for all selected CSNs; CSNs that already have a lorry / are cancelled / not ready are skipped and listed.
- [x] Bulk **Create subsheets**: choose Type (Subsheet = pickup to hub, or Transfer = handover leg) -> Transfer code (incoming codes only for Subsheet) -> Lorries -> route / notes. CSNs without a main lorry are skipped.
- [x] Number column shows tags under the CSN number: transfer code(s) (CSN's own + its subsheets'), **Subsheet** (xN), **Break bulk** (xN).
- [x] **Transfer code** filter moved to the always-visible filter row and now also matches codes on the CSN's subsheets.

- [x] CSN list shows a **Delivery** column (latest main DO: Not assigned / Assigned / In transit / Delivered / **Failed** with the driver's reason and time) and a **Failed delivery** tab (red count) for CSNs whose latest DO failed. Needed because drivers can fail a delivery for any reason (app).
- [?] Bulk subsheet form has a "Type" (Subsheet / Transfer) choice that the user did not ask for (it is the existing subsheet task type). Asked the user whether to remove it and let the transfer code decide; waiting for the answer.

- [x] **QR code on the CSN and DO documents** (PDF + preview): top right, encodes the document number (CSN no. / DO no.) with the number printed under it. QR chosen over a barcode (phone cameras, any angle, damage-tolerant). Helper `App\Support\QrCodeImage` (PNG, error correction M). Verified the printed QR decodes back to the CSN number.

- [x] **Returned CSN scanning on the CSN page** (item 18): header button **Scan returned CSN** opens a window: scan with a handheld scanner / type the number + Enter, **Use camera** (live camera, HTTPS site only), or **Scan from photo** (works everywhere). Each scan marks the CSN returned (`RecordReturnedCsn`); scanning again says "already returned"; **Not returned** undoes it (`UndoReturnedCsn`). New column **CSN returned** (Returned + date / receiver, Not yet, Missing, —). Menu item "Returned CSNs" removed. Camera library vendored at `public/js/og/html5-qrcode.min.js` (Apache-2.0).

### Payments & Receipts / Cash Bill Calculator (item 19)
- [x] "Cash Bill Calculator" removed from the menu; opened from Payments & Receipts -> **Create Cash Bill Payment** (button already there).
- [x] Calculator reworked: pick the **Customer** (only customers with unpaid Cash Bill CSNs, with the count) -> all their unpaid Cash Bill CSNs listed with **checkboxes** (tick / untick / tick all, click the row too); **Scan CSN QR code** box (handheld scanner or type + Enter) ticks that CSN and picks its customer.
- [x] **Payment slips / receipts upload** (images or PDF, 8 MB each, max 10) with thumbnails + remove; saved on every payment of the transaction (payments.slip_path / slip_paths).
- [x] Every payment recorded here appears in the Payments & Receipts listing (verified). Amounts show RM.

### Menu clean-up
- [x] Removed from the sidebar: Shared Dispatch, Break-Bulk Record, Failed Delivery Review, Returned CSNs, Cash Bill Calculator (pages still exist and open from links).
- [x] Transfer Codes moved from Dispatch to **Master Data**.

### Known gaps (not done, mention to the user when relevant)
- A blank consignee still prints the To location name on the CSN.
- Older records (before 6 Oct 2026) with no consignor now show blank instead of the customer name.
- Customer-portal payment submissions do not apply the COD "driver collects" rule.
- Stored `paid_amount` on old rows is not backfilled (the Payment summary card uses the live sum).
- The pricing lookup skips special prices for UOM items (pricing not finalised; leave until the user asks).

## Next steps

1. Wait for the user's next feedback item.
2. Optional: full browser pass of the list pages (light, dark, phone width).
3. Commit and push only when the user asks.

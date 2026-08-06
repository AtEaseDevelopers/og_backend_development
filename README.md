# O&G Transport System

Centralized logistics management platform for O&G Transport Group (KL, JB, Klang, Penang–Selangor).

## Stack

- Laravel 12
- Filament 3 admin (`/admin`)
- Blade customer portal (`/portal`)
- Sanctum driver API (`/api/v1/driver`)
- Spatie Permission + Activity Log

## Setup

```bash
composer install
cp .env.example .env   # if needed
php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm install && npm run build
php artisan serve
```

## Login flow (branch → company)

Admin flow: **login → choose branch → choose or register company (BRN required) → open that company’s system**.

- Brand logo / **Change branch** returns to the branch picker
- **Change company** returns to the company picker for the selected branch
- Seeded demo companies use the same codes as branches (`KL`, `JB`, `KLG`, `PG`)

- `ops@og.local` — one account linked to **all 4 branches**
- `admin@og.local` — HQ, also all branches
- `manager.kl@og.local` — KL only (example of single-branch user)

## Demo accounts

Password for all: `password`

| Role | Email |
|---|---|
| Multi-branch ops | ops@og.local |
| HQ Admin | admin@og.local |
| Single-branch managers | manager.kl@og.local, manager.jb@og.local, … |
| Counters / finance / drivers | counter.{branch}@og.local, finance.…, driver.… |
| Portal Customer | portal@demo.local |

### Seeded per company (KL / JB / Klang / PG)
- 3 drivers + 3 lorries + vehicle maintenance records
- 2 customers (credit + cash) with delivery addresses
- Confirmed quotations, converted CSNs, assigned DOs / job sheets
- Mix of **delivered** (some CSN returned), **in-transit**, COD jobs, and unconverted quotes
- KL→JB shared-dispatch sample

Reseed: `php artisan migrate:fresh --seed`

## Phase 1 acceptance path

1. Open `/admin` and login as `admin@og.local` / `password`
2. Open **Quotations**, open the seeded confirmed quotation
3. Use **Convert** → creates **2 CSNs** (one per destination)
4. Open **Consignment Notes** → **Assign to Lorry** → select `WXY1234`
5. Confirm **Delivery Orders** + **Job Sheets** were created
6. Driver API:

```bash
# Login
curl -s -X POST http://localhost:8000/api/v1/driver/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"driver.kl@og.local","password":"password"}'

# Use token for check-in / complete
curl -s http://localhost:8000/api/v1/driver/job-sheet \
  -H "Authorization: Bearer TOKEN"

curl -s -X POST http://localhost:8000/api/v1/driver/check-in \
  -H "Authorization: Bearer TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"job_sheet_id":1,"latitude":3.14,"longitude":101.68}'

curl -s -X POST http://localhost:8000/api/v1/driver/deliveries/1/complete \
  -H "Authorization: Bearer TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"recipient_name":"Ali","latitude":3.14,"longitude":101.68,"client_uuid":"11111111-1111-1111-1111-111111111111"}'
```

7. Portal: `/portal` as `portal@demo.local` / `password` — view quotation, submit enquiry
8. Public tracking: `/track/{delivery_order.tracking_token}`

## Domain layout

```
app/Domains/
  MasterData/ Quotation/ Consignment/ Dispatch/ Delivery/
  Billing/ Commission/ Integration/ Portal/ Identity/
```

Document chain: **Quotation → CSN → DO → Job Sheet → POD**  
Sticky rule: CSN `source_branch_id` owns billing/commission even when another branch’s lorry executes.

## Phase 2 (billing)

Implemented:
- Credit approvals (`/admin/credit-approval-requests`) — auto-trigger on confirm when credit rules fail
- Cash Bill: collect payment before assign; Cash Bill Calculator; auto invoice + receipt
- COD: proforma on convert; driver full collection on POD; COD Reconciliation page
- Term: month-end consolidation from Invoices → **Month-end Term Billing**
- Payments & Receipts, Statements of Account

Demo finance login: `finance.kl@og.local` / `password`

### Phase 2 acceptance path

1. Convert seeded quotation as **Cash Bill** → Collect Payment on each CSN → Assign to lorry
2. Or convert as **COD** → proforma created → assign → driver complete with `cod_amount_collected` → COD Reconciliation
3. Or convert as **Term** (credit customer) → deliver → Invoices → Month-end Term Billing
4. Force credit approval: lower customer credit limit below quote total, then Confirm quotation

## Phase 3 (dispatch)

Implemented:
- **Shared Dispatch** — assign any CSN to any-branch lorry (select or QR token); sticky source branch preserved
- **Subsheets** + **Transfer Codes** — planned multi-driver / transfer segments with PSI/PSO
- **Break-Bulk** — request (admin or driver API), assign continuation, handover, revoke
- **Failed Deliveries** — reassign as **standard** (same DO) or **duplicate** (new DO)
- **Delivery Monitoring** — 4pm incomplete-task alerts (`og:flag-incomplete-deliveries` at 16:05)
- Job Sheet **Transfer task** for controlled in-transit moves

Demo JB lorry for shared dispatch: `JBB5678`  
Transfer codes: `TRF-KL-JB`, `PSI-JB-IN`, `TRF-KL-PG`

### Phase 3 acceptance path

1. Convert quotation → Assign one CSN via **Shared Dispatch** to `JBB5678` (source KL, lorry JB)
2. On Job Sheet → **Transfer task** to another lorry with a reason
3. Create **Subsheet** with transfer code `TRF-KL-JB` and PSI/PSO amounts
4. Driver API break-bulk:

```bash
curl -s -X POST http://localhost:8000/api/v1/driver/deliveries/1/break-bulk \
  -H "Authorization: Bearer TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"reason":"Axle overload","location":"R&R Sungai Buloh"}'
```

5. Admin **Break-Bulk** → Assign replacement lorry → update handover to completed
6. Fail a delivery via driver API → **Failed Deliveries** → Reassign (standard or duplicate)
7. **Delivery Monitoring** → Flag incomplete deliveries for today

## Phase 4 (returned CSN + commission)

Implemented:
- **Returned CSNs** desk — QR or select pending CSN; signed/stamped; releases draft commission
- **Missing CSNs** — grace period (`OG_MISSING_CSN_DAYS`, default 7); `og:flag-missing-csns` daily 06:30
- **Commission Rules** — rate %, single/split 2–4, optional branch/lorry type
- **Commission Batches** — generate by source branch + month; confirm/lock; carry-forward ineligible lines
- **Commission Slips** — adjustments (COD shortage), hide lines with reason, PSI/PSO totals
- **PO/PI** generation after confirm (AutoCount sync stubbed for Phase 5)

Eligibility: original signed CSN returned. Missing/pending holds payout; failed tasks show **0** on slip.

### Phase 4 acceptance path

1. Deliver a DO (driver complete) → CSN `return_status=pending_return`
2. **Returned CSNs** → record return (or leave it and run **Flag overdue as missing**)
3. **Commission Batches** → Generate for current month / KL
4. Open slip — ineligible lines carry-forward until return; return CSN then rebuild/generate
5. Adjust / hide lines as needed → **Confirm** → **Generate PO/PI**

## Phase 5 (integrations, reports, fleet)

Implemented (adapters run in **simulate** mode by default — swap to live APIs when credentials are ready):

- **AutoCount Sync** — sales invoice / AR receipt / commission PO–PI; sync log; retry; duplicate skip
- **MyInvois e-Invoice** — prepare buyer link, public buyer form `/einvoice-buyer/{token}`, submit, UUID + PDF path, scheduled auto-submit (optional)
- **OCR Quotations** — upload hardcopy → simulated extract → human review → draft quotation
- **Reports** hub — CSN/DO, missing CSN, payments/COD, commission, break-bulk, invoices, AutoCount, e-invoice, vehicle; CSV export
- **Vehicle Maintenance** — service/permit/insurance/road tax/etc + due alerts (`og:flag-vehicle-maintenance-due`)

Env knobs: `OG_AUTOCOUNT_MODE`, `OG_MYINVOIS_MODE`, `OG_MYINVOIS_AUTO_SUBMIT`, `OG_OCR_MODE`, `OG_VEHICLE_ALERT_DAYS`

### Phase 5 acceptance path

1. Create/open an Invoice → **MyInvois** → Prepare → open buyer URL → save buyer → Submit
2. **AutoCount Sync** → sync that invoice → confirm sync log ref
3. **OCR Quotations** → upload any PDF/image → Approve corrections → draft quotation created
4. **Vehicle Maintenance** → add insurance expiry within 30 days → Run due alerts
5. **Reports** → pick a report → Export CSV

## Status

Phases 0–5 foundation complete. Live AutoCount / LHDN credentials and real OCR vendor wiring remain environment-specific follow-ups.

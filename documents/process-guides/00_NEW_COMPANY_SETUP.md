# 00 · New Company Setup (do this before the first bill)

Do these steps once, in order, when a new company is registered in zoloERP.
After step 12 the company can raise its first purchase, GRN, sale, DC, return and voucher.

Paths are written as **Menu › Item** (sidebar) with the URL in brackets.
Press `?` on any page to see its keyboard shortcuts.

---

## Step 1: Create the company

**Login › Company picker › New company** (`/select-company`)

| Field | What to enter | Required |
|---|---|---|
| Legal name | Name as on PAN / GST certificate | Yes |
| Display (trade) name | Name printed on bills | No |
| Short code | Capital letters, digits, `_` or `-` (e.g. `ZTEX`). Leave blank to auto-generate | Yes (auto) |
| Group | Pick a company group, or leave independent | No |
| Industry · business type | General trading, FMCG, Textile, Timber or Solar, then the sub-type | Yes |
| State | Company state (decides CGST+SGST vs IGST) | Strongly recommended |
| Address, city, PIN, phone, email, website | Printed on invoices | Recommended |
| PAN / GSTIN | GSTIN of the company. PAN is taken from it | Needed for GST billing |
| Logo | PNG/JPG for the invoice header | No |
| Currency | INR (default) | Yes |
| Time zone | Asia/Kolkata | Yes |
| Financial year start / end | e.g. 01-04-2026 to 31-03-2027 | Yes |

Click **Save**, then pick the new company in the picker.

### What the system creates for you

| Created automatically | Detail |
|---|---|
| Branch | `MAIN` – Main branch |
| Warehouse | "<Company> - Main" |
| Financial year | The dates you entered, status *open* |
| Chart of accounts | Standard chart (Cash 1010, Bank 1020, Debtors 1100, Inventory 1200, Creditors 2010, GRNI 2060, GDNI 1260, Capital 3010, Retained Earnings 3020, Opening Balance Adjustment 3040, Sales 4010, Sales Returns 4040, Purchase Returns 4050, COGS 5010, Shrinkage 5020, Variance 5030, expenses 6010-6090 …) with posting roles linked |
| GST | Registration (if GSTIN given), standard GST slabs and GST ledgers (CGST / SGST / IGST / cess / round-off) |
| Starter masters | Customer group *General*, category *General*, biller (the company), payment accounts *Cash* and *Bank*, sale types, purchase types, bill sundries, document (voucher) series |
| Industry preset | Modules, product attributes, units (e.g. MTR for textile), process types, print decimals |
| Access | You and the group admins get access to the company and its branch |

---

## Step 2: Company profile

**Settings › Company profile** (`/settings/company`)

1. Check legal name, trade name, address, state, PIN, phone, email, PAN, GSTIN.
2. Upload or change the logo.
3. Save.

---

## Step 3: Financial year

**Workspaces › Setup › Manage financial years** (`/company/financial-years/setup`)

1. Confirm the open year covers the dates you will bill on.
2. If you are entering old bills of a previous year, create that year too. A bill dated outside an open year is refused.

---

## Step 4: GST & tax

**Settings › GST & Tax** (`/settings/gst`)

1. **Registration** – GSTIN, legal name, trade name, type (Regular / Composition / Unregistered), effective from, address. Saving sets the company state and turns GST billing on. **No GSTIN?** Choose type **Unregistered**, leave GSTIN empty and pick the state: bills then print as "Invoice" with no GST, and GST paid to suppliers goes into cost (no input credit). When you get a GSTIN later, save it with its start date; the unregistered period closes the day before.
2. **Install standard GST setup** – only if the GST slabs or GST ledgers are missing.
3. **Invoice print details** – phone, email, bank name, account no., IFSC, branch, UPI ID, signature caption, terms lines. These print on the A4 tax invoice.
4. **Products** tab – set GST rate and HSN/SAC for each item (can also be done in step 9).
5. **Parties** tab – check each customer/supplier GST profile (Regular / Unregistered / Composition, state).

> **Buying from an unregistered supplier (no GSTIN, e.g. a small supplier under the ₹40 lakh goods threshold, including inter-state):** mark the supplier **Unregistered** on the Parties tab and leave the product on its normal GST slab. Do **not** switch the product to *Exempt* or *Non-GST supply* — that is a product-level setting and would also affect your sales of it. With an Unregistered supplier the system charges **no CGST/SGST/IGST** on the purchase and takes **no input credit**.
>
> **Warning:** an unregistered supplier cannot charge GST, so **never enter IGST (or CGST/SGST) on such a bill** — the system will not post it, and if the supplier's bill shows GST, ask them for a corrected bill of supply. Leave *Reverse charge* off unless the purchase is in a category the law notifies for reverse charge. The system does not track the ₹40 lakh limit; the supplier is responsible for staying within it.

**Sales › Tax & document settings** (`/compliance/setup`) – admin only: e-invoice / e-way settings, return approval limits, document settings.

---

## Step 5: Business profile (modules)

**Business profile** (`/operations/profiles`)

Turn on only what this company uses, for example:
- Batch & expiry (FMCG / pharma)
- Multiple units per item
- Job Work (textile, timber processor)
- BOM + Production (manufacturer)
- Dot-matrix printing

Changing the industry here swaps the industry presets (process types, labels such as *Godown*).

---

## Step 6: Warehouses / godowns

**Settings › Warehouse** (`/warehouse`)

1. Rename "<Company> - Main" if needed and fill its address.
2. Add every other godown / shop / store you hold stock in.
3. Add a *quarantine* warehouse if you want rejected returns kept apart.

---

## Step 7: Numbering and transaction masters

**Master Registers** (sidebar)

| Item | Path | What to check |
|---|---|---|
| Voucher Series | Master Registers › Voucher Series (`/document-series`, Ctrl+F9) | Prefix and next number for Sales, Purchase, DC, GRN, PO, SO, Credit note, Debit note, Receipt, Payment, Journal. Set them **before** the first document; numbers are allocated on save |
| Sale Types | Master Registers › Sale Types | e.g. Local, Inter-state, Export, Exempt |
| Purchase Types | Master Registers › Purchase Types | e.g. Local, Inter-state, Import, Unregistered |
| Bill Sundry | Master Registers › Bill Sundry | Freight, packing, discount, round-off lines used on bills |
| Agents & Brokers | Master Registers › Agents & Brokers | Only if you sell/buy through agents |
| Areas | Master Registers › Areas | Routes / areas for DCs and sales |
| Standard Remarks | Master Registers › Standard Remarks | Repeated remarks for bills |

---

## Step 8: Accounts and posting links

1. **Accounting › Chart of Accounts** (`/accounting/chart-of-accounts`) – add ledgers you need (more bank accounts, specific expense heads, loans, fixed assets). New accounts start at zero.
2. **Accounting › Semantic Account Mappings** (`/accounting/semantic-account-mappings`) – every role must point to an account. Check these are **not blank**:

| Role | Default account | Used by |
|---|---|---|
| ar / ap | 1100 Debtors / 2010 Creditors | Every credit sale / purchase |
| cash / bank | 1010 / 1020 | Payments and receipts |
| inventory / cogs | 1200 / 5010 | Stock items |
| sales / discounts | 4010 / 4020 | Sales bills |
| tax_output / tax_input | GST ledgers / 1150 | GST |
| sales_returns / purchase_returns | 4040 / 4050 | Credit / debit notes |
| goods_received_not_invoiced | 2060 | GRN |
| goods_delivered_not_invoiced | 1260 | Customer DC |
| damage (shrinkage) / variance | 5020 / 5030 | Returns, job work loss, price differences |
| freight / goods_in_transit / production_costs / expense | 6050 / 1250 / 5040 / 6090 | Charges, transfers, production |
| opening_balance | 3040 Opening Balance Adjustment | Party opening dues, go-live balances |
| retained_earnings | 3020 Retained Earnings | Year close (profit moves here) |

   If a role is blank, the first document that needs it stops with a message such as *"Configure an active company account mapping for damage before posting."*

3. **Accounting › Account List (Cash)** (`/accounts`) – payment accounts shown in the "Paying account" list on bills. *Cash* and *Bank* exist; add one per real bank account.
4. **Voucher hub › Settlement Account Links** (`/accounting/vouchers`) – link each payment account to its chart ledger (Cash → 1010, each bank → its own 102x ledger).

---

## Step 9: Item masters

**Product › Unit / Category / Brand**, then **Product › Add product** (`/products/create`)

1. Units – check the industry units exist (PCS, BOX, MTR, KG …). Add conversions if you sell in a different unit than you buy.
2. Categories and brands.
3. For each item: name, code, type (*standard* = stock item; *service* = no stock), unit, purchase unit, sale unit, cost, price, **GST rate**, **HSN/SAC**, reorder level, batch/expiry or serial flags if used.
4. Bulk: Product list › import (CSV) for many items. Use product aliases (alternate names / codes) if parties call items by different names.

> GRN and DC accept **standard (stock) items only**. Serial-numbered items are received on the purchase bill and sold on the invoice, not on GRN/DC.

---

## Step 10: Parties

**People › Customer List / Supplier List** (or **+ New Party** inside any bill)

For each party enter: name, GSTIN (state is read from it), state, address, PIN, phone, email, credit days / credit limit, customer group, agent, area.
Same-state party → CGST + SGST. Other state → IGST. No GSTIN → unregistered.

**Opening balance (Due)**: what the party owed you (customer) or you owed the party (supplier) on go-live day. On save it posts on the **first day of the financial year**:
customer → Dr Debtors (party) / Cr 3040 Opening Balance Adjustment; supplier → Dr 3040 / Cr Creditors (party).
It appears as an open item, so receipts and payments can be allocated against it and it shows in Ageing.
If the year start is locked, or the Debtors / Creditors / Opening balance accounts are not mapped, the party is **not saved** and the form says why.

---

## Step 11: Opening balances (only if the business already traded before)

Post everything **dated the first day of the financial year** (or the go-live date) and before any normal bill.

**Option A – migration from another system (recommended for many rows)**
Use the reviewed opening import. It posts stock, receivables, payables and ledger balances through the same posting engine, validates first, and refuses a company that already has balances:

```
php artisan erp:import-opening opening.json --company=<id> --branch=<id> --year=<id> --actor=<user id> --validate
php artisan erp:import-opening opening.json --company=<id> --branch=<id> --year=<id> --actor=<user id> --commit --confirm-reviewed-opening
```

**Option B – small business, by hand**

| Opening item | How | Entry |
|---|---|---|
| Customer dues (one total per customer) | *Opening balance (Due)* on the customer form (step 10) | Dr Debtors (party) / Cr 3040 Opening Balance Adjustment |
| Supplier dues (one total per supplier) | *Opening balance (Due)* on the supplier form (step 10) | Dr 3040 / Cr Creditors (party) |
| Customer / supplier dues bill by bill | Voucher Entry (F9) › F7 Journal, one line per old bill with the **party** and due date | Dr Debtors or Cr Creditors, other side 3040 |
| Cash / bank balances | F7 Journal | Dr Cash / Bank, Cr 3040 |
| Loans, fixed assets, GST credit | F7 Journal | Dr / Cr the ledger, other side 3040 |
| Opening stock | Opening import (Option A), or a purchase bill from an "Opening Stock" supplier dated the start date and then a journal Dr Creditors / Cr 3040 for that supplier | Dr Inventory, Cr 3040 |
| Owner's capital | F7 Journal | Dr 3040 / Cr 3010 Owner's Capital |

When everything is in, **3040 Opening Balance Adjustment** holds the owner's net worth at go-live. Move it to capital (last row) so 3040 ends at zero.
Then check **Accounting › Trial Balance** – debits must equal credits, and Debtors / Creditors must match the party lists.

---

## Step 12: Users, roles and printing

1. **Settings › Role Permission** – create roles (Billing, Store, Accounts, Manager) and tick permissions (sales, purchases, GRN, DC, returns, vouchers, approvals, reports).
2. **People › User List** – create users, give each a role, warehouse and biller.
3. **Settings › Invoice Settings** – invoice layout, A4 / thermal / dot-matrix.
4. **Settings › Receipt Printers** – POS printers if used.
5. **Settings › General Setting / POS Settings** – date format, decimals, default warehouse/customer for POS.

---

## Go-live checklist

- [ ] Company picker shows the company; profile, logo and GSTIN print correctly (print one test invoice and cancel it, or check the preview)
- [ ] Financial year open for the billing dates
- [ ] GST registration saved, slabs and ledgers installed, invoice print details filled
- [ ] Voucher series prefixes set
- [ ] All semantic account roles mapped
- [ ] Payment accounts linked to chart ledgers
- [ ] Items have unit, GST rate and HSN
- [ ] Parties have GSTIN / state
- [ ] Opening balances posted, Trial Balance balanced
- [ ] Users and roles created

Next: [01 Purchase](01_PURCHASE_CYCLE.md) · [02 Sales](02_SALES_CYCLE.md) · [03 GRN](03_GRN_GOODS_RECEIVED_NOTE.md) · [04 DC](04_DC_DELIVERY_CHALLAN.md) · [05 Returns](05_RETURNS.md) · [06 Accounting](06_ACCOUNTING.md)

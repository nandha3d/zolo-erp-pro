# 00A · Setup Steps 7, 8 and 9 in detail (masters, accounts, items)

This is the detailed companion to [00 New Company Setup](00_NEW_COMPANY_SETUP.md), steps 7, 8 and 9.
Do steps 1–6 first. Finish these three steps before the first bill.

---

## Where to find the Master Registers

There are two ways in:

1. **Sidebar › *Sales, Purchases & Inventory* › Master Registers.** It sits just below **Product**. Click it to open the list (Bill Sundry, Sale Types, Purchase Types, Agents & Brokers, Areas, Standard Remarks, Voucher Series).
2. **Workspaces › (company) › Settings › Master registers** panel (`/workspace/setup`). It has the same links.

Voucher Series also opens from any page with **Ctrl+F9**.

**Can't see Master Registers in the sidebar?**

| Cause | Fix |
|---|---|
| You are not an admin and your role has no *Product* menu permission | Admin: **Settings › Role Permission** › your role › tick the Product menu permission (`sidebar_product`) and save. Or use the Workspace › Settings panel |
| Sidebar is in icon-only mode | Click the ☰ button at top left to show labels, or hover the icons |
| An old page is cached in the browser | Press Ctrl+F5 |

**Rules for all six registers:**

- Each company has its own lists. A new company gets a starter list automatically (shown in each section below).
- Each row has **Edit** (pencil) and **Delete** (bin). Edit opens the same form filled in; it also has an **Active** tick box.
- **Delete is permanent**, so it is refused for an entry that bills, DCs, GRNs, customers or suppliers already use. Edit that entry and untick **Active** instead.
- Only *active* entries appear in the drop-downs on bills, DCs and GRNs.

---

## Step 7: Numbering and transaction masters

### 7.1 Voucher Series (document numbering)

**Master Registers › Voucher Series** (`/document-series`, **Ctrl+F9**). Only a company administrator can change it.

Set this **before the first document**. Numbers are taken when a document is saved.

| Field | What to enter | Rules |
|---|---|---|
| Series Type | The document it numbers: Sales, Purchase, Purchase order, Receipt, Payment, Journal, Quotation, Sales return, Purchase return, Delivery challan, GRN, Production, Stock adjustment, Stock transfer, Stock loss, Expense, Job work order / DC / GRN | Required |
| Series Name / Code | A short name, e.g. `SALES`, `SALES-WHOLESALE` | Required. Must be unique in the company |
| Prefix | Text before the number, e.g. `MJ-`, `INV/26-27/` | Optional, up to 100 characters |
| Suffix | Text after the number, e.g. `/A` | Optional, up to 50 characters |
| Start / Next No | First number to issue, e.g. `1`, or `251` to continue numbering from your old software | Required, 1 or more |
| Digits (zero padding) | `5` gives `00001` | Required, 1–18 |
| Default for this type | Tick for the series that bills use when you don't pick one | One default per document type |

Result: prefix + padded number + suffix, e.g. `MJ-` + `00001` = **MJ-00001**.

**Rules:**

- A series belongs to one branch and one financial year. Numbering restarts each financial year.
- A **GST invoice number may have at most 16 characters**, prefix and suffix included.
- Once a series has issued a number, you can only change its *default* tick or **raise** the next number. To use a new format, create a new series and make it the default.
- To keep separate numbering for two kinds of sales (e.g. retail and wholesale), create two Sales series. Pick the series in the *Series* box on the bill.

**Created automatically for the first branch and year:**

| Document | Code | Prefix | Document | Code | Prefix |
|---|---|---|---|---|---|
| Sales (default) | SALES | `MJ-` | Purchase (default) | PURCHASE | `PUR-` |
| Service sales | Service | `MJC-` | Service purchase | SERVICE PURCHASE | `SPUR-` |
| Receipt | RECEIPT | `REC-` | Payment | PAYMENT | `PAY-` |
| Journal | JOURNAL | `JE-` | Quotation | QUOTATION | `QUO-` |
| Sales return | SALES RETURN | `SR-` | Purchase return | PURCHASE RETURN | `PR-` |
| Delivery challan | DELIVERY CHALLAN | `DC-` | GRN | GOODS RECEIVED NOTE | `GRN-` |
| Purchase order | PURCHASE ORDER | `PO-` | Production | PRODUCTION | `PRD-` |
| Stock adjustment | STOCK ADJUSTMENT | `ADJ-` | Stock transfer | STOCK TRANSFER | `TRF-` |
| Stock loss | STOCK LOSS | `LOSS-` | Expense | EXPENSE | `EXP-` |
| Job work order | JOB WORK ORDER | `JWO-` | Job work DC | JOB WORK DC | `JWDC-` |
| Job work GRN | JOB WORK GRN | `JWGRN-` | | | |

All start at 1 with 5 digits. A second branch adds its code (`MJ-CBE-00001`). A later financial year adds its year (`MJ-27-00001`).

**What to do:** replace `MJ-` and `MJC-` with your company's own prefix (e.g. `ZTEX-`). Check the other prefixes. If you are moving from other software mid-year, set *Next No* to continue from your last number.

### 7.2 Sale Types (tax classification on sales)

**Master Registers › Sale Types** (`/sale-type`)

A sale type appears as **Tax Classification** on the sales bill and the delivery challan.

| Field | What to enter | Rules |
|---|---|---|
| Sale Type Name | e.g. `18%-GST`, `Export` | Required, unique |
| Code | Short code, e.g. `SGST18` | Optional |
| Tax Nature | Local / Interstate / Export / SEZ / Exempted | Required |
| GST Rate (%) | One rate for the whole bill, e.g. `18`. Use `0` for a bill with mixed rates | 0–100 |
| Sales Ledger Account | Leave blank | Saved, but sales post to the *sales* role account (4010) (step 8.2) |

**How the bill uses it:**

- **Fixed-rate type** (e.g. *18%-GST*): every line of the bill is locked to 18%.
- **Rate 0 or MultiTax type** (*L/MultiTax*, *Interstate MultiTax*, *Export*, *SEZ Supply*, *Exempted*) and the default **"GST • Multiple rates"**: each line keeps its own item GST rate, and you can change it.
- **CGST + SGST or IGST is decided by the party's state** compared with the company's state, not by the sale type's name. Picking a *Local* type for an other-state party still gives IGST.
- *Export*, *SEZ Supply* and *Exempted* do **not** set the tax to zero by themselves. For a zero-rated or exempt sale, set the line rates to 0%, or use items whose GST rate is exempt / nil.

**Created automatically:** 5%/12%/18%/28%-GST (local), 5%/12%/18%/28%-IGST (interstate), L/MultiTax, Interstate MultiTax, Export, SEZ Supply, Exempted.

**What to do:** most companies need nothing more. Keep "GST • Multiple rates" for normal billing and let item rates decide. Only add a type if you need a new single-rate classification.

### 7.3 Purchase Types (tax classification on purchases)

**Master Registers › Purchase Types** (`/purchase-type`)

A purchase type appears as **Tax Classification** on the purchase bill and the GRN.

| Field | What to enter | Rules |
|---|---|---|
| Purchase Type Name | e.g. `18%-GST Inward` | Required, unique |
| Code | e.g. `PGST18` | Optional |
| Tax Nature | Local / Interstate / Import / Exempted | Required |
| GST Rate (%) | One rate for the bill, or `0` for mixed rates | 0–100 |
| Purchase Ledger Account | Leave blank | Saved, but posting uses the role accounts (step 8.2) |

The lock rule is the same as for sale types. A fixed rate locks every line. A rate of 0 or a MultiTax type leaves each line free.

**Created automatically:** L/MultiTax, Interstate MultiTax, 5%/12%/18%/28%-GST Inward, 5%/12%/18%/28%-IGST Inward, Import, Exempted Inward.

**Unregistered supplier:** there is **no** purchase type for this. Leave the supplier's GSTIN empty, or mark the supplier *Unregistered* in **Settings › GST & Tax › Parties** (step 10). The system then charges no GST and takes no input credit, whatever type you pick.

### 7.4 Bill Sundry (charges and deductions)

**Master Registers › Bill Sundry** (`/bill-sundry`)

| Field | What to enter | Rules |
|---|---|---|
| Sundry Name | e.g. `Freight Charges`, `Trade Discount` | Required, unique |
| Nature | Sales / Purchase / Both | Required |
| Calculation Type | Percentage or Amount | Required |
| Default Value | e.g. `2` (for 2%) or `150` (₹) | Optional |
| GST Tax Rate (%) | GST charged on the sundry, if any | 0–100 |
| Accounting Ledger Head | Ledger the charge belongs to | Optional |
| Affect Item Cost / Sales Rate | Tick if the charge should go into the item's cost (freight on purchase) | |
| Calculate Before Tax | Tick if GST is worked out after this line (trade discount) | |

**Created automatically:** Freight Charges, Loading & Unloading, Insurance Charges, Trade Discount (%), Cash Discount (%), Packaging Charges, Rounded Off (Add), Rounded Off (Sub), Surcharge.

**What bills use today:** the **Charges** tab on the purchase and sales bill has two boxes, **Freight / Transport Charge (₹)** and **Bill Discount (₹)**. You cannot pick from this register on the bill yet. What the two boxes do:

- Freight on a **purchase** is added to the cost of the goods received (landed cost).
- Freight on a **sale** posts to the *freight* role account (4030).
- Bill discount posts to the *discounts* role account (4020).
- Round-off is worked out automatically and posts to the *rounding* role account (6095).

**What to do:** check the list and remove anything you will never use. The register needs no other setup before go-live.

### 7.5 Agents & Brokers

**Master Registers › Agents & Brokers** (`/agent`). Skip this if you don't trade through agents.

| Field | What to enter | Rules |
|---|---|---|
| Agent Name | e.g. `Ramesh Textiles Agency` | Required, unique |
| Agent Code | Short code | Optional |
| Commission Rate (%) | e.g. `2` | 0–100. Stored for reference; commission is **not** calculated automatically |
| Phone Number, Email, Address | Contact details | Optional |
| Commission Payable Account | The ledger you pay commission from | Optional |

**Created automatically:** `DIRECT` (code DIR, 0%), for sales made without an agent.

**Where it is used:**

- **Through Agent** on the delivery challan and the GRN.
- The agent on a party (set when the party is created with *+ New Party* on a bill).
- The **Agent** filter in Ageing and party reports. The report uses the agent on the invoice; if there is none, it uses the party's current agent.

### 7.6 Areas (routes)

**Master Registers › Areas** (`/area`)

| Field | What to enter | Rules |
|---|---|---|
| Area Name | e.g. `Tiruppur Route`, `Coimbatore City` | Required, unique |
| Area Code | e.g. `TPR` | Optional |
| Pincode, City, State | Location | Optional |

**Created automatically:** `Local / Head Office` (code HQ, your company's city and state).

**Where it is used:**

- **Area / Route** on the delivery challan.
- The area on a party.
- The **Area** filter in Ageing and party reports, so you can see dues and sales route by route.

### 7.7 Standard Remarks

**Master Registers › Standard Remarks** (`/standard-remark`)

| Field | What to enter | Rules |
|---|---|---|
| Remark Title | Short name shown in the list, e.g. `Payment terms` | Required, up to 100 characters |
| Applicable To | Sale / Purchase / Voucher / All | Required |
| Remark Content | The full text, e.g. `Payment within 30 days.` | Required, up to 1000 characters |
| Auto-apply as default remark | Marks your usual remark | Saved, but not filled in on new bills yet. Pick the remark by hand |

**Created automatically:**

- *Standard Terms*: "Goods once sold will not be taken back."
- *Interest Clause*: "Interest @ 18% p.a. will be charged if payment is delayed beyond credit days."
- *Jurisdiction*: "Subject to local court jurisdiction only."

**Where it is used:**

- The **Remarks** tab on the purchase and sales bill. Pick a title and its text goes into the bill notes.
- The remark list in **Voucher Entry (F9)**.

---

## Step 8: Accounts and posting links

Do the parts in this order: **8.1** ledgers, **8.2** role mappings, **8.3** payment accounts, then **8.4** links between payment accounts and ledgers.

### 8.1 Chart of Accounts: add the ledgers you need

**Accounting › Chart of Accounts** (`/accounting/chart-of-accounts`)

| Field | What to enter | Rules |
|---|---|---|
| Code | Number in the right range: 1xxx asset, 2xxx liability, 3xxx equity, 4xxx income, 5xxx–6xxx expense. e.g. `1021` | Required, unique |
| Name | e.g. `HDFC Bank CA 50200012345` | Required |
| Type | Asset / Liability / Equity / Revenue / Expense | Required |
| Sub type | e.g. `bank`, `current_asset`, `fixed_asset`, `loan`, `operating_expense` | Required |
| Parent | The group it sits under | Optional |
| Description | Note | Optional |
| Opening balance | Locked at **0** | Enter opening amounts as a journal in step 11 |

**Edit** (pencil) changes the name and description only; code, type and parent stay fixed. **Delete** (bin) works only on a ledger you added that has no postings, no sub-ledgers, no linked payment account, no role mapping and a zero balance. System ledgers show no bin.

Ledgers to add, if the company has them:

| Need | Example code / name | Type · sub type |
|---|---|---|
| Each real bank account | 1021 HDFC Bank CA, 1022 SBI OD | Asset · bank |
| Fixed assets | 1510 Machinery, 1520 Furniture, 1530 Vehicles | Asset · fixed_asset |
| Loans taken | 2510 Bank Term Loan, 2520 Loan from Director | Liability · loan |
| Specific expense heads | 6110 Electricity, 6120 Rent, 6130 Salaries | Expense · operating_expense |
| Other income | 4510 Interest Received | Revenue |

### 8.2 Semantic Account Mappings (posting roles)

**Accounting › Semantic Account Mappings** (`/accounting/semantic-account-mappings`). Also reachable from **Workspace › Settings › Account mappings**. Only a company administrator can change it.

A role tells the system which ledger a document posts to. Each role is filled automatically when the company is created. Check that **none is blank** and change one only if you want a different ledger.

| Role | Default ledger | Used by |
|---|---|---|
| ar | 1100 Accounts Receivable (Debtors) | Every credit sale, receipts |
| ap | 2010 Accounts Payable (Creditors) | Every credit purchase, payments |
| cash | 1010 Cash on Hand | Cash bills, receipts, payments |
| bank | 1020 Bank Accounts | Bank receipts and payments (unless a payment account is linked in 8.4) |
| inventory | 1200 Merchandise Inventory | Stock items on purchase and sale |
| cogs | 5010 Cost of Goods Sold | Cost of stock sold |
| sales | 4010 Sales Revenue | Sales bills |
| discounts | 4020 Sales Discounts & Rebates | Bill discount |
| freight | 4030 Shipping & Delivery Fees Collected | Freight on sales bills |
| rounding | 6095 Round Off | Bill round-off |
| tax_output / tax_input | 2020 Sales Tax Payable / 1150 Input Tax Receivable | General tax control |
| output_tax_cgst / sgst / igst / cess | 2050 / 2051 / 2052 / 2053 | GST on sales |
| input_tax_cgst / sgst / igst / cess | 1310 / 1311 / 1312 / 1313 | GST on purchases (input credit) |
| sales_returns | 4040 Sales Returns | Credit notes |
| purchase_returns | 4050 Purchase Returns | Debit notes |
| goods_received_not_invoiced | 2060 GRNI | GRN before the purchase bill |
| goods_delivered_not_invoiced | 1260 GDNI | Customer DC before the sales bill |
| goods_in_transit | 1250 Goods in Transit | Stock transfers |
| damage | 5020 Inventory Shrinkage & Write-offs | Damage, stock loss, job-work loss |
| variance | 5030 Inventory Variance | Price and quantity differences |
| production_costs | 5040 Production Costs Absorbed | Production |
| expense | 6090 General & Miscellaneous Expense | Expenses with no other head |
| opening_balance | 3040 Opening Balance Adjustment | Party opening dues, go-live balances |
| retained_earnings | 3020 Retained Earnings | Year-end close |

**Rules:**

- The ledger you pick must be **active** and of the right type. A role for a liability needs a liability ledger, for example. A wrong type is refused when you save.
- The ledgers mapped to **ar** and **ap** become *control* ledgers. Only party documents post to them; a manual journal cannot.
- If a role is blank, the first document that needs it stops with a message such as *"Configure an active company account mapping for damage before posting."*

### 8.3 Payment accounts (the "Paying account" list on bills)

**Accounting › Account List** (`/accounts`)

The company starts with *Cash* and *Bank*. Add one payment account for each real bank account:

| Field | What to enter |
|---|---|
| Account No | Bank account number |
| Name | e.g. `HDFC CA` – this appears in the *Paying account* list on bills |
| Initial Balance | Leave **0**. Put the opening balance in a journal (step 11), so the books show it once only |
| Note | Branch, IFSC, etc. |

### 8.4 Settlement Account Links

**Accounting › Voucher hub** (`/accounting/vouchers`) › open **Period controls and settlement account links** › **Settlement Account Links**

For each payment account from 8.3, select its ledger from 8.1 and save:

| Payment account | Link to ledger |
|---|---|
| Cash | 1010 Cash on Hand |
| Bank | 1020 Bank Accounts (or your main bank ledger) |
| HDFC CA | 1021 HDFC Bank CA |
| SBI OD | 1022 SBI OD |

An unlinked payment account posts to the company's default **cash** or **bank** ledger (8.2), so all your banks would land in one ledger. Link each one so each bank reconciles on its own.

---

## Step 9: Item masters

Order: **units → categories → brands → items**, then fill GST gaps. Paths are in the sidebar under **Product**. The Product pages also have a tab bar along the top (Product list, Alternate names, Reviewed import, Category, Brand, Unit…).

### 9.1 Units

**Product › Unit** (`/unit`)

The industry preset already created the usual units (e.g. PCS, BOX, KG; MTR for textile). Add any that are missing.

| Field | What to enter |
|---|---|
| Unit Code | e.g. `BOX` |
| Unit Name | e.g. `Box` |
| Base Unit | Leave empty for a base unit. For a pack unit, pick the unit it is made of, e.g. `PCS` |
| Operator | `*` (multiply) or `/` (divide) |
| Operation Value | How many base units, e.g. `12` |

Example: *1 BOX = 12 PCS* → Code `BOX`, Base Unit `PCS`, Operator `*`, Value `12`. Then an item with unit PCS can be bought in BOX and sold in PCS. Stock is always kept in the base unit.

### 9.2 Categories and brands

- **Product › Category** (`/category`): every item needs a category. Group items the way you want to report them (e.g. *Shirting*, *Suiting*, *Yarn*). A category can hold a default HSN, used when an item is saved without one.
- **Product › Brand** (`/brand`): optional. Use it if you report or price by brand.

### 9.3 Add product, field by field

**Product › Add product** (`/products/create`)

| Field | What to enter | Notes |
|---|---|---|
| Product Type | **Standard** = stock item · **Service** = no stock (labour, freight, job charges) · **Combo** = a kit of other items · **Digital** | Only *Standard* items go on GRN and DC |
| Product Name | Name as printed on the bill | Required |
| Product Code | Your item code, or click to generate one | Required, unique (inactive items count) |
| Barcode Symbology | Code128 (default) | Matters only if you print barcodes |
| Brand | From 9.2 | Optional |
| Category | From 9.2 | Required |
| Product Unit | The base (stock) unit, e.g. `PCS`, `MTR` | Required for Standard |
| Purchase Unit / Sale Unit | The unit you buy / sell in, e.g. buy `BOX`, sell `PCS` | Must belong to the base unit (9.1) |
| Product Cost | Purchase rate per base unit | Required |
| Product Price | Selling rate per base unit | Required |
| Wholesale Price | Optional second price | |
| Alert Quantity | Reorder level; the item shows in low-stock alerts below this | |
| Negative stock | Whether a sale may take stock below zero | |
| **GST rate** | Pick the slab (0%, 5%, 12%, 18%, 28%, exempt…). *Manage* opens GST settings | Needed for GST billing |
| **HSN / SAC code** | Goods: 4, 6 or 8 digits. Services: `99xxxx` | Blank = category default on creation |
| Tax Method | **Exclusive** = price + GST · **Inclusive** = price already contains GST | |
| Batch and expiry | Tick for FMCG / pharma items | Then purchase and sale lines ask for batch and expiry |
| IMEI / Serial numbers | Tick for serial-tracked goods | Received on the purchase bill and sold on the invoice, **not** on GRN / DC |
| Variants | Tick for size / colour variants | |
| Different price per warehouse | Tick if the price varies by godown | |
| Initial stock | Leave unticked for a new company | Opening stock goes in step 11 |
| Image, details, warranty | Optional | |

### 9.4 Alternate names

**Product › Product list › Alternate names** tab. Add the names or codes a supplier or customer uses for your item. Searching on a bill finds the item by any of its names.

### 9.5 Bulk items: Reviewed import

**Product › Product list › Reviewed import** tab (`/products/import/review`)

1. Click **Download CSV template**.
2. Open **Company master IDs for the CSV** on the same page to see the ID of each category, brand, unit and GST rate.
3. Fill one row per item:

| Column | What to enter | Required |
|---|---|---|
| name | Item name | Yes |
| code | Item code, unique | Yes |
| type | `standard`, `service` or `digital` | Yes |
| category_id | Category ID from the IDs box | Yes |
| brand_id | Brand ID | No |
| unit_id, sale_unit_id, purchase_unit_id | Unit IDs (standard items only) | Yes for standard |
| cost, price | Rates | No (0) |
| tax_category_id | GST rate ID | Recommended |
| hsn_code | HSN / SAC | Recommended (blank = category default) |
| tax_id | Old tax ID; leave empty when using GST | No |
| negative_stock_policy | Leave empty for the default | No |

4. Upload the file and click **Preview rows**. Every row is checked. If any row is wrong, nothing is created; fix the file and upload it again.
5. Tick **I reviewed every row** and confirm.

Limits: UTF-8 CSV, up to 500 rows and 1 MB per file. Import only **creates new** items; it never changes an existing item. Opening stock, variants and images are not imported. Add them in the item form or in step 11.

### 9.6 Fill GST gaps in one go

**Settings › GST & Tax › Products** tab (`/settings/gst`)

The tab lists every item still missing a GST rate or HSN/SAC. Pick the rate and type the HSN on each row, then click **Save products**. Step *3. Products* at the top of the page turns green when every item has both.

---

## Done when

- [ ] Voucher series prefixes are your own and *Next No* is right (7.1)
- [ ] You know which sale/purchase type to use; usually "GST • Multiple rates" (7.2, 7.3)
- [ ] Agents, areas and remarks added if you use them (7.5–7.7)
- [ ] One ledger and one payment account per real bank account, linked to each other (8.1, 8.3, 8.4)
- [ ] No blank role in Semantic Account Mappings (8.2)
- [ ] Units and conversions exist; every item has a category, unit, GST rate and HSN (9.1–9.6)

Next: [00 New Company Setup](00_NEW_COMPANY_SETUP.md), step 10 (Parties).

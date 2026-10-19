# 01 · Purchase Cycle (Purchase Order → GRN → Purchase Bill → Payment)

Screen: **Purchase Command Center** (`/purchases`). One screen holds the bill form, the draft tabs and the bill list.

| Key | Opens |
|---|---|
| F12 | New purchase bill |
| Ctrl+F12 | New purchase order |
| Alt+F12 | New GRN (goods received note) |
| Shift+F12 | New purchase return |
| Alt+I | Item search row |
| F6 | Create a new item (inside the form) |
| Ctrl+B | Pending bills of the selected supplier |
| Ctrl+S | Last rates for the supplier + item |
| Alt+Y | Supplier statement |
| Ctrl+Enter | Save |

Before you start: [00 New Company Setup](00_NEW_COMPANY_SETUP.md) steps 1-10 are done.

---

## Which route to use

| Situation | Route |
|---|---|
| Goods and supplier invoice arrive together | **Route A** – Purchase bill directly (F12) |
| Goods arrive first, invoice later (or rate not final) | **Route B** – GRN (Alt+F12), then convert to bill |
| You place an order first and want to track pending quantity | Add a **Purchase Order** before Route A or B |
| Services (freight, job work charges, repairs) | Route A only – GRN takes stock items only |

---

## Step 1 (optional): Purchase Order

1. Press **Ctrl+F12** (or Purchase Command Center › Purchase Orders › New).
2. Supplier, order date, expected date, warehouse.
3. Add lines: item, unit, qty, rate.
4. **Save**. The order gets its own PO number. It has **no** stock or accounting effect.
5. Status moves open → partial → fulfilled as GRNs / bills use its lines. Amend it, or cancel the unfulfilled remainder, from the same screen.

---

## Route A: Purchase bill directly

### Step A1: Open a new bill
Press **F12** (or **+ New** in the Purchase Command Center). A blank draft tab opens with focus on the supplier.
Drafts save themselves every 1.5 s; you can leave and come back. A draft never moves stock or posts accounts.

### Step A2: Header
| Field | Enter |
|---|---|
| Payment | **Cash** (paid now) or **Credit** (pay later) |
| Our Bill No | Leave as shown – the real number is given on save |
| Supplier Inv No | Supplier's invoice number (needed for GST input credit) |
| Bill Date | Supplier invoice date |
| Entry Date | Today (posting date) |
| Supplier | Pick, or **+ New Party**. Check the GSTIN / address / credit days strip |
| Tax classification / Purchase type | Local / Inter-state / Unregistered … |
| Series | Leave default unless you keep several series |
| Warehouse / Godown | Where the goods go |

### Step A3: Lines
1. **Alt+I**, type item code / name / barcode, Enter.
2. Unit, Qty, Rate (or type the Amount and the rate is calculated).
3. Batch / expiry / serial / lot if the item needs it (row detail).
4. Item discount if any.
5. Repeat. Use **Multi item** to pick many items at once. When billing against a purchase order, load the order's remaining lines into the bill so the PO quantity is tracked.

### Step A4: Charges and transport
**Charges & remarks** (bottom bar): freight, packing, other bill sundries, round-off, transporter, LR no./date, vehicle no., bales/bundles, remarks.

### Step A5: Review and save
1. Click **Review** – the server recalculates taxes and shows the grand total.
2. Check CGST+SGST (same state) or IGST (other state).
3. **Save** (Ctrl+Enter). The bill posts and gets its number, e.g. `PUR-00001`.
4. For a **Cash** bill choose the payment method and paying account in the payment section before saving.

**What gets posted**

| Debit | Credit |
|---|---|
| Inventory (stock items) or expense (service items) – taxable value | Creditors (supplier) – grand total |
| Input CGST + SGST, or Input IGST | |
| Freight / charges ledger (if any) | |

Stock increases in the chosen warehouse at the bill rate.

---

## Route B: GRN first, then bill

### Step B1: Record the GRN
Follow [03 GRN](03_GRN_GOODS_RECEIVED_NOTE.md). Stock comes in now at the GRN rate:
Dr Inventory / Cr Goods Received Not Invoiced (2060).

### Step B2: Convert the GRN to a purchase bill
1. GRN screen (`/goods-received-notes`) › open list panel › on the GRN row click **Convert** (or open it and click **Convert to Purchase**).
2. The purchase bill opens with the **open (unbilled) quantity** of each line.
3. Enter the supplier invoice no. and date, correct the rate if the invoice differs, add charges.
4. **Review** › **Save**.

**What gets posted**: stock is **not** received again.
Dr GRNI (at GRN value) + Input GST / Cr Creditors. If the bill rate differs from the GRN rate, the difference revalues the stock still on hand and the part already sold goes to COGS.

You can bill a GRN in parts. The GRN status moves pending → partially billed → converted to purchase.

---

## Step 2: Pay the supplier

**Option 1 – from the bill list**
1. Purchase Command Center › bill list › row **action** menu › **Add Payment**.
2. Amount (defaults to the due amount), date, payment method (Cash / Bank / Cheque / Card), paying account, cheque no., note.
3. Save. Posts Dr Creditors / Cr Cash or Bank (e.g. `PAY-00001`). Bill status → Partial or Paid.

**Option 2 – one payment against many bills**
Accounting › Voucher hub (`/accounting/vouchers`) › Voucher type **Payment** › template *Supplier bill payment* › pick the supplier › **Allocate Against Outstanding Bills** › Apply. See [06 Accounting](06_ACCOUNTING.md).

---

## Step 3: Check

| Check | Where |
|---|---|
| Bill status (Due / Partial / Paid) | Purchase Command Center bill list |
| Supplier balance | People › Supplier List › Ledger, or Alt+Y in the bill |
| Stock | Product › Product List / Stock by lot |
| Journal | Accounting › Journal Entries or General Ledger |
| GST input | Sales › GST review (`/compliance/gst/report`) |

---

## Change or cancel

- **Correct a bill**: bill list › **✎ Edit** loads it into the form; **Save** reposts it (the old stock and journal are reversed for you). If the edit is refused because the bill already has payments or returns, reverse those first.
- **Cancel a bill completely**: bill list › red **↩ Reverse** icon on the bill card (shown to users allowed to delete purchases), give date and reason, submit. A reversal journal and stock-out are posted; the bill shows **Reversed** and stays in the list for audit.
- Goods sent back after billing: [05 Returns](05_RETURNS.md) › Purchase return / debit note.
- Wrong rate after billing (supplier gives a rate difference / discount): Purchase return with adjustment *Rate difference* or *Discount*.

## Common messages

| Message | Fix |
|---|---|
| *"Configure an active company account mapping for … before posting."* | Map that role in Accounting › Semantic Account Mappings |
| *"The reference must belong to the selected product."* | Remove and re-add the line (stale batch reference in an old draft) |
| Date refused / period locked | Bill date is outside the open financial year or before the lock date |
| GST is IGST instead of CGST+SGST | Supplier state / GSTIN differs from the company state – correct the supplier |

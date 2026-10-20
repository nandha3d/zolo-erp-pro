# 02 · Sales Cycle (Quotation → Sales Order → DC → Sales Bill → Receipt)

Screen: **Sales Command Center** (`/sales`). One screen holds the bill form, the draft tabs and the bill list.

| Key | Opens |
|---|---|
| F2 | New sales bill |
| Alt+F2 | New customer DC (delivery challan) |
| Shift+F2 | New sales return |
| Alt+F10 | New quotation |
| Alt+I | Item search row |
| F6 | Create a new item (inside the form) |
| Ctrl+B | Pending bills of the selected customer |
| Ctrl+S | Last sale rates for the customer + item |
| Alt+Y | Customer statement |
| Ctrl+Enter | Save |

Before you start: [00 New Company Setup](00_NEW_COMPANY_SETUP.md) is done and the items have **stock** (a sale is refused if stock is short: *"Insufficient stock for product … : 0 available, 2 requested."*).

---

## Which route to use

| Situation | Route |
|---|---|
| Normal sale – goods leave with the invoice | **Route A** – Sales bill directly (F2) |
| Goods leave before the invoice (on approval, quantity known only at delivery, not a supply) | **Route B** – Customer DC (Alt+F2), then convert to bill |
| Customer wants a price offer first | Start with a **Quotation** |
| You take an order and deliver later / in parts | Add a **Sales Order** |
| Counter sale | POS Terminal (Sales › POS Terminal) |

> GST rule: a regular sale needs the **tax invoice at the time of dispatch**. Use a DC only for the Rule 55 purposes listed in [04 DC](04_DC_DELIVERY_CHALLAN.md).

---

## Step 1 (optional): Quotation

1. **Alt+F10** (or Sales › Quotations › New).
2. Customer, date, validity, warehouse, items, rates, discount, terms.
3. Save. Track follow-up date and status in the quotation list.
4. When accepted: quotation row › **Create Sale** – opens a sales bill filled from the quotation.

## Step 2 (optional): Sales Order

1. Sales Command Center › **Sales Orders** › New.
2. Customer, order date, delivery date, items, rates.
3. Save. No stock or accounting effect. Delivery (by DC) and billing are tracked against the order lines.

---

## Route A: Sales bill directly

### Step A1: Open a new bill
Press **F2**. A blank draft tab opens with focus on the customer. Drafts save themselves; they never move stock or post accounts.

### Step A2: Header
| Field | Enter |
|---|---|
| Payment | **Cash** (paid now) or **Credit** |
| Bill No | Shown as preview – the real number is given on save |
| Customer PO | Customer's PO number, if any |
| Bill date | Invoice date |
| Customer | Pick, or **+ New Party**. Check GSTIN, address, credit days, outstanding |
| Tax classification / Sale type | Local / Inter-state / Export / Exempt |
| Series, Warehouse, Biller | Defaults are fine for most users |

### Step A3: Lines
1. **Alt+I**, type code / name / barcode, Enter.
2. Unit, Qty, Rate (Ctrl+S shows the last rate for this customer).
3. Choose batch / lot / serial if the item uses them (lots are listed with their stock in that warehouse).
4. Discount per line if any. Repeat.

### Step A4: Charges and transport
**Charges & remarks**: freight, packing, discount, round-off; transporter, LR no./date, bale no., no. of bales, no. of bundles, vehicle no., destination, remarks. These print on the invoice.

### Step A5: Review and save
1. **Review** – server recalculates; shows *"Reviewed by server. Grand total: ₹ …"*.
2. Check CGST+SGST (same state) or IGST (other state) and round-off.
3. For **Cash**: choose payment method (Cash / Bank / Cheque / Card) and paying account.
4. **Save** (Ctrl+Enter). The bill posts and gets its number.

**What gets posted**

| Debit | Credit |
|---|---|
| Debtors (customer) – grand total (or Cash/Bank for a cash bill) | Sales – taxable value |
| COGS – stock cost (weighted average) | Output CGST + SGST, or Output IGST |
| | Freight / charges collected, round-off |
| | Inventory – stock cost |

### Step A6: Print / send
Bill list › **Print** (A4 tax invoice with GSTIN, HSN, tax split, amount in words, bank and UPI details, transport details). **View** on the bill opens the document page with A4 / thermal / dot-matrix / PDF print options. Send by mail / SMS / WhatsApp if configured.

---

## Route B: DC first, then bill

1. Make the Customer DC – see [04 DC](04_DC_DELIVERY_CHALLAN.md). Stock goes out now: Dr Goods Delivered Not Invoiced (1260) / Cr Inventory.
2. DC screen (`/delivery-challans`) › list panel › row **Convert** (or open the DC › **Convert to Sale**).
3. The sales bill opens with the **open (unbilled) quantity** of each DC line. Add rates if missing, charges, transport.
4. **Review** › **Save**.

**What gets posted**: stock is **not** issued again. Dr Debtors / Cr Sales + Output GST, and Dr COGS / Cr GDNI at DC cost.
A DC can be billed in parts: pending → partially billed → converted to sale.

---

## Step 3: Receive payment

**Option 1 – from the bill list**
1. Sales Command Center › bill list › row **action** › **Add Payment**.
2. Amount (defaults to due), date, method, paying account (bank / cash), cheque no., note.
3. Save. Posts Dr Cash/Bank / Cr Debtors (e.g. `REC-00001`). Status → Partial or Paid.

**Option 2 – one receipt for many bills / advance**
Accounting › Voucher hub › Voucher type **Receipt** › template *Customer bill receipt* › pick the customer › **Allocate Against Outstanding Bills** (or post on account as an advance and allocate later). See [06 Accounting](06_ACCOUNTING.md).

**Follow-up of dues**: Accounting › Ageing (`/accounting/ageing`) and Collections (`/accounting/collections`) – follow-up dates, PDC (post-dated cheque) register, reminder preview.

---

## Step 4: Check

| Check | Where |
|---|---|
| Bill status | Sales Command Center bill list (Due / Partial / Paid) |
| Customer outstanding | Ctrl+B / Alt+Y in the bill, or People › Customer List › Ledger |
| Pending sales / orders | Sales Command Center › Pending Sales / Sales Orders |
| Dispatch status | Sales › Dispatch Register |
| GST output | Sales › GST review (`/compliance/gst/report`), export for returns |

---

## Change or cancel

- **Correct a bill**: bill list › **✎ Edit** loads it into the form; **Save** reposts it (old stock and journal reversed for you). If refused because the bill has receipts or returns, reverse those first.
- **Cancel a bill completely**: bill list › red **↩ Reverse** icon on the bill card (shown to users allowed to delete sales), give date and reason, submit. Reversal journal posted, stock comes back, bill shows **Reversed**. After GST filing for that month, use a credit note instead of cancelling.
- **Copy a bill**: bill list › **Copy** starts a new draft with the same lines.
- Goods returned after billing, rate difference, extra discount, tax correction: [05 Returns](05_RETURNS.md) › Sales return / credit note.

## Common messages

| Message | Fix |
|---|---|
| *"Insufficient stock for product … in warehouse …"* | Post the purchase / GRN first, or pick the warehouse that has the stock |
| *"Configure an active company account mapping for … before posting."* | Accounting › Semantic Account Mappings |
| IGST charged to a same-state customer | Customer GSTIN / state is wrong – fix the customer |
| Reprinted invoice still shows "Balance due" | It shows the balance at posting time; check the status in the bill list |

# 05 · Returns (Sales Return / Credit Note, Purchase Return / Debit Note, unbilled GRN/DC returns)

Pick the right return first:

| What happened | Document | Where |
|---|---|---|
| Customer returns goods that were **invoiced** | Sales return – **Credit note** (quantity return) | Sales Command Center › Returns (Shift+F2) |
| Customer gets a rate reduction / extra discount / tax correction on an invoice | **Credit note** (financial – rate difference / discount / tax correction) | Same |
| Customer must pay more on an invoice (rate increase, under-billed tax) | **Debit note** against the sale | Same |
| You return goods to a supplier that were **billed** | Purchase return – **Debit note** (quantity return) | Purchase Command Center › Returns (Shift+F12) |
| Supplier gives a rate difference / discount after the bill | **Debit note** (financial) | Same |
| Supplier charges more after the bill | **Credit note** against the purchase | Same |
| Goods received on a GRN go back **before billing** | GRN › **Return to supplier** | [03 GRN](03_GRN_GOODS_RECEIVED_NOTE.md) Step 3 |
| Goods sent on a DC come back **before billing** | DC › **Goods came back** | [04 DC](04_DC_DELIVERY_CHALLAN.md) Step 3 |

Returns always point to the **original posted bill**. Tax is taken from the original bill's tax snapshot, so the GST rate and CGST/SGST/IGST split match the invoice.

---

## A. Sales return / credit note

### Step 1: Open the return against the bill
Any one of:
- Sales Command Center › bill list › row action › **Add Return**
- Bill › **View** › **Create return or financial note**
- **Shift+F2** › Returns register › Add return › enter the bill number

### Step 2: Fill the note

| Field | Enter |
|---|---|
| Note date | Date of the return (not before the bill date) |
| Note type | **Credit – reduce invoice** (normal return) or Debit – increase invoice |
| Adjustment | **Quantity return** (goods come back), **Rate difference**, **Discount** or **Tax correction** |
| Reason | Required, 3-500 characters (e.g. "Damaged in transit – 5 m returned") |

For each original line you are returning:
1. Tick the line.
2. **Return quantity** (quantity return) – at most the billed quantity.
3. **Financial change before tax** (rate difference / discount) – the taxable amount to reduce.
4. **Disposition**: Restock, Quarantine, Damaged, Expired or Quality reject.
5. **Return warehouse**: default is the bill's warehouse; pick a quarantine godown for rejects.
6. Serial / batch: tick the exact serial or lot coming back if listed.

### Step 3: Submit
Click **Submit note**.
- Normal notes post at once and get a credit note number.
- **High-value or late notes go to *Awaiting Approval***. Until approved, stock and accounts are unchanged.

### Step 4: Approve (approver only)
Sales Command Center › **Returns** › *Awaiting Approval* card › on the note click **Approve & Post**.

**What gets posted (credit note, quantity return)**

| Debit | Credit |
|---|---|
| Sales Returns (4040) – taxable value | Debtors (customer) – note total |
| Output CGST + SGST / IGST (reversed) | |
| Inventory – cost of goods restocked | COGS |

Damaged / rejected dispositions go to the damage / shrinkage account instead of saleable stock.
Rate difference / discount notes post only the value and tax lines – no stock moves.

### Step 5: Settle the credit
- Customer still owes on other bills: Accounting › Voucher hub › allocate the credit note against open bills (see [06 Accounting](06_ACCOUNTING.md) – Allocation).
- Refund cash: Voucher hub › **Payment** voucher › Dr Debtors (customer) / Cr Cash or Bank, allocate against the credit note.

---

## B. Purchase return / debit note

### Step 1: Open the return against the purchase bill
- Purchase Command Center › bill list › row action › **Add Return**
- Bill › **View** › **Create return or financial note**
- **Shift+F12** › Returns register › Add return › enter the bill number

### Step 2: Fill the note
Same fields as the sales return. Note type **Debit – reduce invoice** for goods going back to the supplier or a supplier discount.
Disposition is usually *Restock* reversal (goods leave your stock); pick the warehouse the goods leave from.

### Step 3: Submit, then approve if it is waiting
Purchase Command Center › **Returns** › *Awaiting Approval* › **Approve & Post**.

**What gets posted (debit note, quantity return)**

| Debit | Credit |
|---|---|
| Creditors (supplier) – note total | Inventory / Purchase Returns (4050) – taxable value |
| | Input CGST + SGST / IGST (reversed) |

Stock goes out of the chosen warehouse.

### Step 4: Settle
- Adjust against the next supplier bill: Voucher hub › allocation of the debit note against the open bill.
- Supplier refunds money: Voucher hub › **Receipt** › Dr Bank / Cr Creditors (supplier), allocated to the debit note.

---

## C. Print and send

Returns register › note row › print. The credit / debit note prints with the original invoice number and date, reason, HSN, tax split – send it to the party.

## D. Rules to remember

- Posted notes **cannot be edited or deleted**. Make a new opposite note if a note is wrong.
- A return can't be dated before its bill, and not in a locked period.
- Return quantity can't exceed what was billed less what was already returned.
- GRN / DC goods not yet billed are **not** returned here – use the GRN / DC return buttons.
- Exchange (customer swaps an item): post the return, then the new sale; the exchange screen links the two.

## E. Check

| What | Where |
|---|---|
| All notes, totals, waiting approvals | Sales › Returns & notes (`/compliance/returns`) |
| Party balance after the note | Customer / Supplier ledger, Alt+Y |
| GST effect | Sales › GST review – credit/debit notes section |

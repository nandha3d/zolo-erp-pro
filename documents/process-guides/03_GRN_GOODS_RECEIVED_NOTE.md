# 03 · GRN – Goods Received Note (supplier goods in, bill later)

Use a GRN when goods arrive from a **supplier** before (or without) the supplier's invoice.
Stock comes in **now**; the money side is settled later by the purchase bill.

Screen: **Supplier GRN** (`/goods-received-notes`) – open with **Alt+F12**, or the *Supplier GRN* button on the Purchase Command Center.

> Material coming back from a **job worker** is not a supplier GRN. Receive it as a **Job Work GRN** inside its Job Work Order (Product › Job Work).

Rules:
- Stock (standard) items only. Services are billed directly on a purchase bill.
- Serial-numbered (IMEI) items are received on the purchase bill, not on a GRN.

---

## Step 1: Enter the GRN

1. Press **Alt+F12**. A new GRN draft opens.
2. Header:

| Field | Enter |
|---|---|
| GRN No | Leave blank for auto number |
| GRN Date | Date goods were received |
| Supplier | Pick, or **+ New** |
| Warehouse / Godown | Where the goods are stored |
| Purchase Type | Local / Inter-state … |
| Through Agent | Optional |
| Purchase Order No | Supplier PO / your PO reference, if any |
| Transporter, LR / Bale No | From the transporter's receipt |

3. Lines: **Alt+I** to search (or *Multi item*, or *+ Create item*). For each line: unit, **rate** (expected cost), qty received, lot / batch no.
4. **Transport** drawer: LR date, remarks & inspection notes (shortage, damage seen at receipt).
5. **Save**.

**What gets posted**

| Debit | Credit |
|---|---|
| Inventory – qty × GRN rate | Goods Received Not Invoiced (2060) |

Stock is now available to sell. The GRN status is **pending**.

---

## Step 2: Supplier sends back nothing – convert to bill

When the supplier invoice arrives:

1. GRN screen › **list panel** (toggle button top right) › find the GRN (search by no. or party).
2. Click **Convert** (or open the GRN with **Edit** and click **Convert to Purchase**).
3. The purchase bill opens with the **unbilled** quantities. Enter Supplier Inv No, bill date; fix rate if the invoice differs; add freight/charges.
4. **Review** › **Save**.

| Debit | Credit |
|---|---|
| GRNI (2060) – at GRN value | Creditors – grand total |
| Input GST | |
| Price difference: stock on hand (Inventory) / sold share (COGS) | |

Partial invoices are fine – convert again later for the rest.
Status: pending → **partially billed** → **converted to purchase**.

Then pay the supplier – [01 Purchase](01_PURCHASE_CYCLE.md) Step 2.

---

## Step 3 (if needed): Return unbilled goods to the supplier

Rejected on inspection, excess supply, wrong item – **before** they are billed:

1. Open the GRN (list panel › **Edit**).
2. Click **Return to supplier**.
3. Enter the quantity per line (at most the unbilled qty), date and **reason** (3-500 characters).
4. Confirm.

| Debit | Credit |
|---|---|
| GRNI – at GRN value | Inventory – at current average cost |
| | Difference → Inventory Variance (5030) |

Status becomes **returned** when nothing is left open.
Goods already **billed** go back through a purchase return / debit note – see [05 Returns](05_RETURNS.md).

---

## Edit or cancel a GRN

- Allowed only while **nothing is billed or returned** on it. Editing reverses the old posting and posts the new version. A cancelled GRN is kept as *cancelled* (not deleted).
- If part is billed or returned: *"Billed or returned goods cannot be changed; reverse the bill or return first."*

## Check

| What | Where |
|---|---|
| Open (unbilled) GRNs | GRN list panel – status pending / partially billed |
| Unbilled value | Accounting › General Ledger › 2060 Goods Received Not Invoiced (should go to zero as bills come in) |
| Stock | Product › Product List / Stock by lot |

# 04 · DC – Customer Delivery Challan (goods out, bill later)

Use a Customer DC when goods leave for a **customer** before the tax invoice, in the cases GST allows (Rule 55).
Stock goes out **now**; the sale is settled later by the sales bill.

Screen: **Customer DC** (`/delivery-challans`) – open with **Alt+F2**, or the *Customer DC* button on the Sales Command Center, or Sales › Dispatch Register.

> Material sent to a **job worker** is not a customer DC. Send it as a **Material DC** inside its Job Work Order (Product › Job Work).

Rules:
- Stock (standard) items only. Serial-numbered (IMEI) items are sold on the invoice, not on a DC.
- A **purpose** is required. A normal sale must go on a tax invoice instead.

| Purpose (Rule 55) | Example |
|---|---|
| On approval / sale or return | Samples or lots sent for the customer to choose |
| Quantity known only at delivery | Bulk / loose goods weighed or measured at the customer's site |
| Not a supply (exhibition, repair, own use) | Goods to an exhibition, for repair, to own branch use |

---

## Step 1: Enter the DC

1. Press **Alt+F2**. A new DC draft opens.
2. Header:

| Field | Enter |
|---|---|
| Challan No | Leave blank for auto number |
| Challan Date | Dispatch date |
| Customer | Pick, or **+ New** |
| Warehouse / Godown | Goods leave from here |
| Sale Type | Local / Inter-state … |
| **Challan Purpose (Rule 55)** | One of the three above |
| Through Agent, Area / Route | Optional |
| Transporter, LR / Bale No | Transport details |

3. Lines: **Alt+I** to search (or *Multi item*). Unit, rate (for value), qty, tax % (for the printed value), lot / batch.
4. **Transport** drawer: bale / package no., no. of bales, LR date, destination station, remarks & delivery notes.
5. **Save**. Print the DC for the transporter.

**What gets posted**

| Debit | Credit |
|---|---|
| Goods Delivered Not Invoiced (1260) – stock cost | Inventory – stock cost |

Status **pending**.

---

## Step 2: Customer accepts – convert to bill

1. DC screen › **list panel** › find the DC › **Convert** (or open it › **Convert to Sale**).
2. The sales bill opens with the **unbilled** quantities. Confirm rates, add charges/transport.
3. **Review** › **Save**.

| Debit | Credit |
|---|---|
| Debtors – grand total | Sales + Output GST |
| COGS – at DC cost | GDNI (1260) – at DC cost |

Bill in parts if the customer keeps part now and part later.
Status: pending → **partially billed** → **converted to sale**.

Then collect payment – [02 Sales](02_SALES_CYCLE.md) Step 3.

---

## Step 3 (if needed): Goods came back unbilled

Customer returns approval goods, exhibition stock comes back, repair done:

1. Open the DC (list panel › **Edit**).
2. Click **Goods came back** (return button).
3. Quantity per line (at most the unbilled qty), date, **reason**.
4. Confirm.

| Debit | Credit |
|---|---|
| Inventory – at DC cost | GDNI (1260) |

Status becomes **returned** when nothing is left open.
Goods already **invoiced** come back through a sales return / credit note – see [05 Returns](05_RETURNS.md).

---

## Edit or cancel a DC

- Allowed only while nothing is billed or returned. Editing reverses and reposts; cancelled DCs are kept as *cancelled*.
- Otherwise: *"Billed or returned goods cannot be changed; reverse the bill or return first."*

## Check

| What | Where |
|---|---|
| Open DCs (goods out, not billed) | DC list panel – pending / partially billed; Reports › Challan Report |
| Value outside | Accounting › General Ledger › 1260 Goods Delivered Not Invoiced |
| Dispatch / delivery | Sales › Dispatch Register (DC, packing slips, deliveries) |

Tip: follow up open approval DCs regularly – goods on approval are still your stock (in 1260) until invoiced or returned.

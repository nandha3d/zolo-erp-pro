# Textile Job Work Cycle: End-to-End Test

Run on 13-10-2026 in the dev app (`http://localhost:8080`), company **zoloERP Textiles** (Textile · In-house processing).
Every step went through the normal screens in Chrome (Playwright). No step was done with a script against the database.
Party names, GSTINs and numbers are made up.

**Answer to "was the job work done, and was the job worker paid?"** Yes, both, in the app:
- 1,000 m of grey fabric went to the dyer on a Material DC.
- 980 m of dyed fabric came back, and 20 m (2%) was booked as shrinkage.
- The dyer's ₹15,435 service bill was booked and **paid by bank** (PAY-00001).
- 500 m was sold and the customer paid.

The books balance: total debits = total credits = ₹99,435.

Eight bugs stopped or broke the cycle and were fixed along the way (section 3). The three design gaps found were then decided and implemented (section 4), and the route was run again end to end (section 5).

---

## 1. The story

| Step | Screen | What happened | Result |
|---|---|---|---|
| 1 | Supplier / Customer › Add | Grey fabric supplier **Kumaran Grey Fabrics** (Erode), job worker **Bluewave Dyeing Works** (Tiruppur), customer **Trendline Garments** (Coimbatore), all Tamil Nadu with GSTINs | 3 parties |
| 2 | Purchases › Create item | **Grey Cotton Fabric 60s** (MTR, GST 5%, HSN 5208); **Dyed Cotton Fabric Royal Blue** (MTR, 5%, HSN 5208); service **Dyeing Charges** (5%, SAC 998821) | 3 items |
| 3 | Purchases (F12) | 1,000 m grey @ ₹80 from Kumaran; transport Erode Roadways, LR ER-5521, 10 bales, vehicle TN33 X 4455 | **PUR-00001** ₹84,000 (80,000 + CGST 2,000 + SGST 2,000) |
| 4 | Job Work › Process Policy | New process **Dyeing - Royal Blue**: *Convert to other products*, max loss 3% | process DYE-RB |
| 5 | Job Work › New Order | Job worker Bluewave, process Dyeing - Royal Blue, expected return 20-10-2026 | order ERP-JWO-…-000001 |
| 6 | Order › **Material DC** (send) | 1,000 m grey sent from the Godown | dispatch ERP-JWD-…-000001; grey moved to the job worker's location |
| 7 | Order › **Job Work GRN** (receive) | Accepted 980 m, loss 20 m (2% is within the 3% limit); output 980 m Dyed Royal Blue | receipt ERP-JWR-…-000001; 980 m dyed in stock at ₹78,400; loss ₹1,600 to Inventory Shrinkage |
| 8 | Order › Service bill | 980 m × ₹15 Dyeing Charges | **PUR-00002** ₹15,435 (14,700 + CGST 367.50 + SGST 367.50) on Bluewave's account |
| 9 | Purchases register › Add Payment | Paid Bluewave ₹15,435 by bank (NEFT); paid Kumaran ₹84,000 by bank | **PAY-00001**, **PAY-00002**; both bills Paid |
| 10 | Sales (F2) | 500 m dyed @ ₹150 to Trendline; KPN Parcel Service, LR KPN-88741, bale no. TB-01..05, 5 bales, 20 bundles, vehicle TN39 AB 1234 | **MJ-00001** ₹78,750 (75,000 + CGST 1,875 + SGST 1,875) |
| 11 | Sales register › Add Payment | Trendline paid ₹78,750 into the bank | **REC-00001**; sale Paid |
| 12 | Print | A4 tax invoice under zoloERP Textiles with transport, LR, bales, bundles, vehicle; amount in words | [tax-invoice-ZTEX-MJ-00001.pdf](tax-invoice-ZTEX-MJ-00001.pdf) |

Screenshots for each step are in this folder (`03-…` to `18-…`).

### Stock at the end
| Item | Godown | At job worker |
|---|---:|---:|
| Grey Cotton Fabric 60s | 0 | 0 |
| Dyed Cotton Fabric Royal Blue | **480 m** | — |

### Journal entries
| # | Document | Debit | Credit |
|---|---|---|---|
| 108 | PUR-00001 | Inventory 80,000 · Input CGST 2,000 · Input SGST 2,000 | Creditors 84,000 |
| 109 | Job work loss | Inventory Shrinkage 1,600 | Inventory 1,600 |
| 110 | PUR-00002 (dyeing) | **General & Misc. Expense 14,700** · Input CGST 367.50 · Input SGST 367.50 | Creditors 15,435 |
| 111 | PAY-00001 (Bluewave) | Creditors 15,435 | Bank 15,435 |
| 112 | PAY-00002 (Kumaran) | Creditors 84,000 | Bank 84,000 |
| 113 | MJ-00001 | Debtors 78,750 · COGS **40,000** | Sales 75,000 · Output CGST 1,875 · Output SGST 1,875 · Inventory 40,000 |
| 114 | REC-00001 | Bank 78,750 | Debtors 78,750 |

The bank is ₹20,685 overdrawn because the test entered no opening capital.

---

## 2. How the job worker payment works in zoloERP

1. **Material DC**: own material moves from the Godown to the job worker's location, and stays company stock.
2. **Job Work GRN**: what comes back is received. With a conversion process, the input is consumed and the new item is added at the input's cost. Loss beyond accepted quantity goes to Inventory Shrinkage, and a loss above the process limit is refused.
3. **Service bill**: from the receipt, the job worker's charge becomes an ordinary purchase bill for a service item, so it sits on the job worker's account with input GST.
4. **Payment**: the bill is paid like any purchase (Purchases register › action › Add Payment). This posts Creditors to Bank.

---

## 3. Bugs found and fixed during the run

| # | Where | Symptom | Fix |
|---|---|---|---|
| 1 | Supplier / Customer create | The 2nd supplier without an email was refused ("email has already been taken"), and names were checked across **all companies** | `App\Support\CompanyUnique`: party name/email/phone are unique only among the current company's active parties, and blanks are never duplicates |
| 2 | New company | No customer group (so no customer could be added), no biller, no Cash/Bank account, no category, no sale/purchase types or bill sundries | `CompanyDirectoryService::installStarterMasters()` runs when a company is created; a migration filled in companies created earlier. It only fills a master the company has none of |
| 3 | Create item (service) | "Database temporarily unavailable" (`profit_margin` cannot be null) | The margin defaults from cost and price |
| 4 | Job Work GRN with loss | **"Configure an active company account mapping for damage before posting."** The standard chart had no account for 9 posting roles: shrinkage, variance, sales/purchase returns, freight, goods in transit, input tax, production costs, general expense | Accounts added to the standard chart (5020 tagged as shrinkage, plus 1150, 1250, 4040, 4050, 5030, 5040). Setup now fills **unmapped** roles; a migration repaired existing companies |
| 5 | Job work / production forms | The error above appeared as a full crash page | Setup errors now come back to the form as a message (`10a-receive-error-message.png`) |
| 6 | New company with a GSTIN | GST ledgers (CGST/SGST/IGST/cess/round-off) were unmapped until someone clicked "Install standard GST setup", so the first GST bill would fail | Registering a GSTIN now creates and maps the GST ledgers |
| 7 | Purchases **and** Sales register › Add Payment | The payment box opened **under** the dark overlay (couldn't be clicked), and it had no amount or bill id because the handler was lost in the command-center redesign | The box now sits above the overlay; restored handler fills the bill and due amount (sent from the server as `data-due`) |
| 8 | Sales / Purchases register row **action** menu | The menu opened upward and was clipped by the table area, so Print / Add Payment were hidden behind the filter bar. This was finding #3 in the sample-bill doc; the items existed but were covered | Register row menus are pinned to the screen next to their button; checked that every item is visible and clickable on both pages at 860 px and 520 px window heights |

---

## 4. Design gaps: decided and implemented (13-10-2026)

| # | Gap | Decision | What changed |
|---|---|---|---|
| A | The dyeing charge was booked as General & Misc. Expense; the dyed fabric stayed at grey cost (₹80/m). | **Add it to the stock cost** (option 1) | When the job worker's bill is posted, its processing charge (before GST) is spread over what came back on that receipt. The part still in stock goes to **Inventory**; any part already sold goes to **Cost of Goods Sold**. Nothing is left in expense. A value-only stock-ledger entry raises the item's average cost. The bill, the inventory/COGS split and the revaluation are one journal, so reversing the bill undoes all of it. The receipt shows "material ₹X + processing ₹Y = ₹Z per unit". |
| B | The Material DC print was not a GST job-work challan (it showed "Job worker #70"). | **Make it GST-compliant** | New **Delivery Challan for job work** (Rule 55(1)(c), Section 143): company GSTIN and address; job worker's **name, address, state, GSTIN**; challan no./date; job work order; process and purpose; expected return; place of supply; item, **HSN**, lot, quantity + unit, rate, **taxable value**; value in words; tax note; vehicle, transporter, **e-way bill no.**; instructions; one-year return note; receiver and authorised signatures. Prints **3 copies** (consignee / transporter / consignor), plus a matching 80-column dot-matrix version. New numbering is `JWO-`, `JWDC-`, `JWGRN-`; numbers already issued stay as they were. |
| C | The textile presets only returned the same item. | **Configurable grey → dyed process** | New textile preset **GREY-DYE "Dyeing - grey to dyed fabric"**: converts to a new item, 5% loss limit. Every process can now store a **default output item, processing-charge item and rate**, which pre-fill the receipt and the bill (each stays editable). You can load and edit any process from Process Policy. Once a process has been used, its loss limit and conversion are locked, but its defaults can still change. Stock lines take a **lot / batch no.** (dyed lots need no expiry date), the receipt records **lots sent → lots received**, and Sales lines choose the lot from a list of lots in that godown. More processes (printing, finishing, knitting…) are added the same way. |

## 5. Run 2: the configured route end to end

Same company and parties, using the GREY-DYE route with defaults: output Dyed Cotton Fabric Royal Blue, charge item Dyeing Charges, ₹15/m.
Screenshots are in [run2/](run2/).

| Step | What happened | Result |
|---|---|---|
| 1 | Process Policy › edit GREY-DYE › defaults set | `01-grey-dye-route-defaults.png` |
| 2 | Purchase 600 m grey @ ₹82 from Kumaran as **lot GREY-L02** (no expiry) | PUR-00003 ₹51,660 |
| 3 | Job work order on GREY-DYE to Bluewave, return by 25-10-2026 | **JWO-00002** |
| 4 | Send 600 m, lot GREY-L02, vehicle TN39 CD 5678, transporter, e-way bill 1810 5566 7788, shade instructions | **JWDC-00002**: GST challan, 3 copies (`05-…`, `05b-…`) |
| 5 | Receive 588 m accepted + 12 m loss (2% ≤ 5%); output item pre-filled; **lot RB-L02** | JWGRN-00002; loss ₹984 to Inventory Shrinkage; lots GREY-L02 → RB-L02 |
| 6 | Job worker bill: item, qty 588 and rate ₹15 pre-filled | **PUR-00004** ₹9,261 → **Dr Inventory ₹8,820** (not expense) + input GST. Receipt shows *material ₹82.00 + processing ₹15.00 = ₹97.00 per unit* |
| 7 | Sale 300 m to Trendline @ ₹160, lot RB-L02 chosen from the list ("RB-L02 · 588") | MJ-00002 ₹50,400; COGS ₹26,807.87 |

**Why COGS in step 7 is ₹89.36/m and not ₹97/m:** zoloERP values stock at the **weighted average per item**, not per lot.
The item also held 480 m from run 1 at ₹80, because run 1's dyeing bill was posted before this change and stayed in expense.
The average is (480 × 80 + 588 × 97) ÷ 1,068 = ₹89.36. In a fresh company, or for any bill from now on, the processing charge is always inside the average.
Run 1's ₹14,700 is historical. To move it into stock, reverse PUR-00002 and its payment, then book the bill again from the receipt.

The books balance after run 2: inventory ₹68,628.13 equals 768 m × ₹89.36 on hand.

Smaller notes:
- A reprinted invoice still shows "Balance due" from posting time, even after payment.
- The product master "cost" field is not the stock value; the books use the stock-ledger average.

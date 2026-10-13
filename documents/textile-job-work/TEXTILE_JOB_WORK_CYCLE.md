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

Eight bugs stopped or broke the cycle and were fixed along the way (section 3). Three design gaps are left for a decision (section 4).

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

## 4. Open design gaps (need your decision)

| # | Gap | Effect | Option |
|---|---|---|---|
| A | The **dyeing charge is not part of the dyed fabric's cost**. The ₹14,700 went to General & Misc. Expense; the dyed fabric is valued at ₹80/m (grey cost only). | COGS for the 500 m sold was ₹40,000 instead of ₹47,500 (₹95/m). The 480 m in stock is undervalued by ₹7,200 at period end. Accounting standards (AS 2 / Ind AS 2) count conversion costs as part of inventory cost. | (1) Add the service bill to the cost of that receipt's output (best). (2) At least book it to a dedicated "Job Work / Processing Charges" account instead of General & Misc. |
| B | The **Material DC print is not a full GST job-work challan**. It shows "Job worker #70" (no name, address or GSTIN), and has no company GSTIN, HSN, value or purpose. The number looks like `ERP-JWD-14-2-2-000001`. | Can't legally travel with the goods (GST Rule 55; job-work stock is also reported in ITC-04) | Print the job worker's name/GSTIN/address, HSN, quantity, taxable value, "sent for job work" purpose and signature; use a DC series |
| C | The ready-made textile processes (Dyeing / Printing / Finishing) return the **same item** | Grey → dyed (a new item) needs a custom process (step 4) | Decide whether textile defaults should include conversion processes |

Smaller notes:
- A reprinted invoice still shows "Balance due" from posting time, even after payment.
- The dyed fabric's product master cost shows 0, although the stock value in the books is correct.

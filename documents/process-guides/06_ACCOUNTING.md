# 06 · Accounting (Vouchers, Bill Settlement, Books, Reports, Period Close)

Every purchase, sale, GRN, DC, return and payment posts its own journal automatically.
This guide covers what accounts staff do **on top of** that: vouchers, settling bills, checking books, GST, and month / year close.

| Screen | Path | Use |
|---|---|---|
| Voucher Entry | Accounting › Voucher Entry · **F9** (`/accounting/voucher/entry`) | Fast keyboard entry: **F4 Contra, F5 Payment, F6 Receipt, F7 Journal** |
| Voucher hub | `/accounting/vouchers` (link from Day Book / Collections) | Vouchers with bill allocation, open bills, period lock / close, settlement account links, reversals |
| Chart of Accounts | Accounting › Chart of Accounts | Add / edit ledgers |
| Journal Entries | Accounting › Journal Entries | Every posted journal, drill-down |
| Day Book / Cash Book | `/accounting/day-book`, `/accounting/cash-book` | Daily checking |
| Ageing | `/accounting/ageing` | Receivables / payables by age, reconciliation |
| Collections | `/accounting/collections` | Follow-up, PDC register, reminder preview |
| Reports | Accounting › General Ledger, Trial Balance, Profit & Loss, Balance Sheet, Cash Flow Statement | Statements |

---

## 1. Daily vouchers

### 1.1 Receipt from a customer (F6)
1. **F9** › **F6 Receipt** (or Voucher hub › Voucher type *Receipt* › template *Customer bill receipt*).
2. Posting date, voucher number (auto), cheque no./date if any.
3. Line 1: **Debit** Cash or Bank – amount.
4. Line 2: **Credit** Debtors – pick the **party** (customer).
5. Reference mode **Against reference** › **Allocate Against Outstanding Bills** › enter the amount against each open bill (or *Skip bill* to keep it on account as an advance).
6. Narration › **Save**.

Posts Dr Bank / Cr Debtors and closes the selected bills (status → Paid / Partial).

### 1.2 Payment to a supplier (F5)
Same as above, reversed: **Credit** Cash / Bank, **Debit** Creditors with the **supplier** as party, allocate against open purchase bills. Template *Supplier bill payment*.

> A single bill can also be paid from the bill list (**Add Payment**) – see [01](01_PURCHASE_CYCLE.md) / [02](02_SALES_CYCLE.md). Use vouchers when one payment covers several bills, or for advances.

### 1.3 Expenses (F5 Payment)
Dr expense ledger (Rent 6020, Utilities 6030, Wages 6010, Bank charges 6060 …), Cr Cash / Bank. Narration with bill / reference.
(Supplier-billed expenses with GST are better entered as a purchase bill of a **service** item so input GST is captured.)

### 1.4 Contra – cash ↔ bank (F4)
Template *Cash deposited in bank*: Dr Bank / Cr Cash. Withdrawal: Dr Cash / Cr Bank. Contra needs two different cash/bank accounts and nothing else.

### 1.5 Journal (F7)
Anything that is not cash/bank: opening balances, depreciation, provisions, corrections between ledgers, write-offs, salary payable.
Debits must equal credits. Put the **party** on any Debtors / Creditors line.

Rules enforced: Payment must credit cash/bank; Receipt must debit cash/bank; every line needs a non-zero amount; system-only ledgers that don't allow manual posting are refused.

---

## 2. Settling (allocating) open bills

Open items = unpaid bills, unused advances, credit/debit notes.

1. Voucher hub › **open bills** list (per party).
2. Allocate an advance receipt / credit note against an invoice, or a debit note against a purchase bill: choose both items › amount › **Apply Allocations**.
3. Wrong allocation: reverse the allocation from the same list (with reason). The vouchers themselves stay.

Check with **Ageing** – a party with a credit and an open bill of the same amount still unallocated shows on both sides until you allocate.

---

## 3. Collections follow-up (receivables)

1. **Ageing** (`/accounting/ageing`): pick Customers, as-of date → buckets (0-30, 31-60, 61-90, 90+).
2. **Collections** (`/accounting/collections`): for each customer record follow-up date, promise, notes; enter **PDCs** (post-dated cheques) with cheque date; preview reminder / interest / discount.
3. When the cheque clears: **Post receipt in Voucher Hub** (Receipt voucher dated the clearing date).

Previews do not post interest or send messages by themselves.

---

## 4. Daily / weekly checks

| Check | Where | Expect |
|---|---|---|
| Cash in hand | Cash Book | Matches physical cash |
| Bank | General Ledger › bank ledger | Matches bank statement (tick cheques cleared) |
| Day's entries | Day Book | Every bill / voucher of the day, nothing unexpected |
| Goods received not invoiced | General Ledger › 2060 | Only GRNs still waiting for supplier bills |
| Goods delivered not invoiced | General Ledger › 1260 | Only DCs still out on approval |
| Debtors / Creditors | Ageing | Agrees with party ledgers |

---

## 5. Month end

1. All bills, GRNs, DCs, returns of the month entered; pending return approvals approved.
2. Bank reconciled to the statement; contra / bank charges / interest entered.
3. Journals: depreciation, salary payable, provisions, accruals.
4. **GST**: Sales › **GST review** (`/compliance/gst/report`) – check outward (sales, credit/debit notes), inward (purchases with supplier invoice no.), HSN summary; **export** for GSTR-1 / GSTR-3B. Pay GST: Payment voucher Dr Output GST ledgers / Cr Bank (after setting off input credit by Journal: Dr Output GST / Cr Input GST).
5. **Trial Balance** – debits = credits; no odd balances (negative cash, wrong-side debtors).
6. **Profit & Loss** and **Balance Sheet** for the month.
7. **Lock the period**: Voucher hub › Period › *Lock through date* = month end › reason › **Update Period**. Nothing can be posted on or before that date until unlocked.
8. Periodic inventory only: Accounting › Periodic Inventory Close – post the closing stock valuation (skip if you run perpetual stock, which is the default).

---

## 6. Year end

1. Finish month-end steps for the last month.
2. Physical stock count: Product › Stock Count → finalize → adjustments post shrinkage / variance.
3. Final journals: depreciation, closing provisions, write-offs of bad debts (Dr expense / Cr Debtors with party).
4. Review Trial Balance, P&L, Balance Sheet, Cash Flow.
5. Create the next financial year (`/company/financial-years/setup`) so new-year bills can be entered.
6. **Close financial year**: Voucher hub › Period › *Close financial year (profit to Retained Earnings)* › reason.
   - A closing journal is posted on the last day of the year (one per branch): every income and expense account is brought to zero and the net profit is credited (loss debited) to **3020 Retained Earnings**.
   - The year is then locked to its end date and marked closed.
   - Profit & Loss and Trial Balance for that year still show the year's income and expenses (the closing journal is left out of them). The Balance Sheet shows the profit inside Retained Earnings.
   - Refused if the account balances and open items do not reconcile – fix those first.
7. Need a correction later: *Unlock / reopen year* (with reason, admin permission). Reopening reverses the closing journal. Post the correction, then close again – a fresh closing journal is posted. All period actions are logged.

---

## 7. Correcting mistakes

| Mistake | Fix |
|---|---|
| Wrong voucher | Voucher hub › posted journals list › **Reverse** (reversal date + reason), then post the right one. Posted journals are never edited |
| Wrong bill | Edit / reverse the bill itself ([01](01_PURCHASE_CYCLE.md) / [02](02_SALES_CYCLE.md)); never patch it with a journal |
| Wrong allocation | Reverse the allocation (Section 2) |
| Posted in a locked period | Unlock (admin), fix, re-lock |
| *"Configure an active company account mapping for … before posting."* | Accounting › Semantic Account Mappings – map the role |

---

## 8. Reports at a glance

| Report | Shows |
|---|---|
| General Ledger / Monthly ledger | Every entry on one account, with running balance |
| Trial Balance | All account balances; debits = credits |
| Profit & Loss | Income – expenses for a period |
| Balance Sheet | Assets = liabilities + equity at a date |
| Cash Flow Statement | Cash movement by operating / investing / financing |
| Day Book / Cash Book | Entries by day / cash and bank movement |
| Ageing | Party dues by age |
| GST review | Outward, inward, notes, HSN – export for returns |

# zoloERP Process Guides

Step-by-step guides for running a company in zoloERP, start to finish.

| # | Guide | Covers |
|---|---|---|
| 00 | [New Company Setup](00_NEW_COMPANY_SETUP.md) | Everything to do once, before the first bill |
| 01 | [Purchase Cycle](01_PURCHASE_CYCLE.md) | Purchase order → GRN → purchase bill → supplier payment |
| 02 | [Sales Cycle](02_SALES_CYCLE.md) | Quotation → sales order → DC → sales bill → receipt |
| 03 | [GRN](03_GRN_GOODS_RECEIVED_NOTE.md) | Supplier goods in before the bill; convert; return unbilled goods |
| 04 | [DC](04_DC_DELIVERY_CHALLAN.md) | Customer goods out before the bill (Rule 55); convert; goods came back |
| 05 | [Returns](05_RETURNS.md) | Credit notes, debit notes, approvals, settlement |
| 06 | [Accounting](06_ACCOUNTING.md) | Vouchers, bill allocation, collections, checks, GST, month / year close |

## Whole flow on one page

```
SETUP (00) ─► masters, GST, series, accounts, opening balances
                     │
   BUY               │                SELL
   PO (Ctrl+F12)     │                Quotation (Alt+F10) ─► Sales Order
     │               │                     │
   GRN (Alt+F12) ──┐ │                DC (Alt+F2) ──┐
     │  stock in   │ │                  stock out   │
   Purchase bill (F12) ◄─ Convert      Sales bill (F2) ◄─ Convert
     │                                     │
   Payment (Add Payment / F5)          Receipt (Add Payment / F6)
     │                                     │
   Debit note (Shift+F12)              Credit note (Shift+F2)
                     │
               ACCOUNTING (06) ─► allocation, books, GST review, period lock, year close
```

## Keyboard map

| Key | Action | Key | Action |
|---|---|---|---|
| F2 | Sales bill | F12 | Purchase bill |
| Alt+F2 | Customer DC | Alt+F12 | Supplier GRN |
| Shift+F2 | Sales return | Shift+F12 | Purchase return |
| Alt+F10 | Quotation | Ctrl+F12 | Purchase order |
| F9 | Voucher entry | F4 / F5 / F6 / F7 | Contra / Payment / Receipt / Journal (inside voucher entry) |
| Alt+I | Item search | F6 | New item (inside a bill) |
| Ctrl+B | Pending bills of party | Ctrl+S | Last rates |
| Alt+Y | Party statement | Ctrl+Enter | Save |
| Ctrl+F9 | Voucher series | ? | Shortcut help |

# Textile P2 implementation

Authorized on 2026-10-19 after local migration and push of P0/P1 checkpoint `4767352` to `origin/ui`.

The existing Commercial, Product/GST and Accounting owners remain authoritative. Each slice must validate company,
branch, permissions and source identity at writes; preserve posted history; and include focused tests and browser proof
for visible changes. New migrations are additive and rehearsed before application to the local database.

## Delivery slices

1. **Receivables and collections.** Extend historical ageing rather than calculating a second current-balance ledger.
   Add configurable ageing cutoffs, party/invoice/due-date/days-overdue display, agent/area/salesman/ledger-group filters
   and matching export. Credits remain explicit and report totals reconcile to the same as-of open items. Add scoped
   follow-up notes/date, recorded PDCs and due reminders, with audit history and optimistic update protection where
   records are amended. Interest and cash-discount calculations are labelled previews, use explicit operator inputs,
   and never post, allocate, reduce outstanding or send messages. Validate PDC lifecycle without treating an uncleared
   cheque as cash. Verify historical allocations/reversals, foreign references, preview rounding and no posting effects.
2. **Alternate product names.** Store company-owned aliases linked to stable products, optionally restricted to one
   customer or supplier. Integrate existing product search, preserve normal code/name matches, scope party aliases and
   return distinct candidates when names are ambiguous. Provide management in the existing product workspace; test
   duplicate rules, Unicode normalization, foreign products/parties and ambiguous search without silent selection.
3. **Reviewed bulk item creation.** Reuse normal product/GST/UOM validation and writing. Provide a CSV template,
   bounded upload, complete row preview with validation/duplicates, and explicit all-or-nothing confirmation. Revalidate
   when committing a preview; a retry must not create duplicates or bypass a changed master. Import is company-scoped
   and creates no stock/accounting entries. Verify partial-invalid files, foreign references, duplicate codes and retries.
4. **Quotation follow-up.** Add a next follow-up date and register filter in the existing quotation workflow. Preserve
   follow-up history through edits and conversion. Keep drafts and company ownership intact. Verify date validation,
   scope, filtering, conversion history and browser creation/edit/filter behavior. No external notifications are implied.
5. **Confirmed bug fixes and integration.** Fix the existing NavigationWebTest route-status failure at its responsible
   owner, then address reproducible regressions revealed by the affected tests and browser flows. Record findings and
   proof; do not claim that testing proves the absence of every possible defect. Run affected suites sequentially,
   apply the new local migrations after a fresh backup, and commit/push verified increments.

## Completed implementation

All five authorized slices are implemented. The P0/P1 checkpoint was pushed first; P2 extends the same shared
Commercial, Inventory, Product/GST and Accounting owners. No separate Textile transaction engine was introduced.

- Historical ageing retains dated allocation/reversal semantics, adds configurable buckets and scoped dimensions,
  exports every filtered row and displays branch-wide AR/AP reconciliation. Collection records retain immutable
  events and optimistic versions. PDC clearing links a valid, same-party receipt with sufficient capacity; a later
  receipt reversal is visibly flagged. Interest and cash-discount previews use exact decimal arithmetic. Operational
  tracking does not post, allocate, send external messages or treat an uncleared cheque as cash.
- Product aliases support company, customer and supplier scopes, normalized duplicate checks and ambiguous results.
  Normal entry and POS preserve product/variant identity, warehouse pricing and stale-response protection. Purchase
  cost is absent from restricted Sales projections and embedded page data. Alias management lives in Product.
- The reviewed UTF-8 CSV importer replaces immediate legacy import. Shared ProductMasterService owns normal, quick
  and bulk validation/writing. Preview retains all input columns and validation results. Confirmation rechecks owned
  references, code uniqueness and GST/master fingerprints under locks. All rows commit together; retries return the
  same products. Imports create zero stock/journal entries and remain visible in existing nonvariant selectors.
- Quotations retain next follow-up dates in drafts, edits, filters and immutable audit history. Register search stays
  inside date/company/staff filters, All no longer truncates at 500, and details use structured escaped data. New Sent
  quotations can convert once; conversion stores the consuming document identity atomically. Converted or unresolved
  historical quotations cannot be deleted, including through a mixed selection. Ordinary pending deletion remains.
- Confirmed integration defects found during tests/browser review were fixed: alias shell middleware, restricted cost
  exposure, POS warehouse/variant pricing, stale alias response bodies, product metadata/markup handling, PDC precision
  and receipt capacity/reversal state, numeric CSV credits, quotation audit/search/export/conversion/deletion, and
  unsupported unit route versus disabled-capability response expectations.

## Verification record

| Verification | Result |
| --- | --- |
| Full `phpunit.commercial.xml` | 160 tests, 924 assertions, 7 expected skips. Six require the separately executed real-MySQL race configuration; one is the existing retired-capability skip. |
| Full `phpunit.documents-web.xml` | 32 tests, 404 assertions passed. Includes restricted actual Sales HTML, first-request alias shell and direct document view fixtures. |
| Accounting filter `CollectionTrackingTest\|AccountingHardeningTest\|CompanyAccountingWebTest` | 45 tests, 330 assertions, 2 MySQL-only skips. |
| Final quotation regression class | 12 tests, 53 assertions passed, including conversion retry, legacy migration replay and atomic protected deletion. Included in the full commercial result. |
| Reviewed import class | 7 tests, 31 assertions passed. Included in the full commercial result. |
| Shared product defaults regression | 11 tests, 84 assertions passed after the shared creation changes. |
| JavaScript suite | 22 passed, including alias ambiguity, deferred response bodies and stale POS context. |
| Browser workspaces/collections/ageing/import/aliases | 23 checks passed; no page JavaScript errors. Desktop and mobile import controls exercised. |
| Browser quotation create/draft/edit/filter/audit/clear/details/invoice conversion | 14 checks passed after migration000016; no page JavaScript errors. |
| Independent specification and quality review | All reported P2 findings resolved. Final quotation and per-slice reviews report no outstanding findings. |

Browser runs use only the disposable existing-data clone `zolo_test_textile_uat_20261018`. Ignored proof files are
`scratch/textile-p2-browser-proof.json`, `scratch/textile-p2-quotation-proof.json`, their scripts/screenshots and
`scratch/textile-p2-ledger-proof.json`. The independent ledger proof records zero journal change, zero stock change,
zero imported quantity and both new products visible to legacy selectors for the operational/import browser flow.
The separate quotation conversion flow intentionally posts a normal invoice through the existing commercial owner.
No external reminders or quotation emails were sent by these browser flows.

## Local migration and rollout

Migrations 000012–000015 were rehearsed and applied locally after verified encrypted database-only backup
`erp-backup-de219a91-61fb-4821-b1fc-be204c3ab543.zip` (196 tables). Migration 000016 was rehearsed and applied locally
after another verified encrypted backup `erp-backup-094b8eb5-dc58-4d94-a42d-faa01d80694e.zip` (201 tables;
SHA256 `5fa1aa81d0201b392958104d7d6a9329685584c8bd6fa997f3a43c7dc5b0976f`). Archives, receipts and separate
keys remain ignored/private. Additive migrations retain posted history on rollback; do not drop their audit tables.

Historical status 2 meant either Sent or Converted and retained no reliable consuming-document link. Migration 000016
flags those existing records for reconciliation and blocks duplicate conversion/deletion. It does not guess invoice
identity. Newly created Pending/Sent quotations use explicit conversion tracking. Accountant review of historical Sent
records is required before any deliberate reopening; no live-data inference or automatic accounting backfill occurred.

This completes the authorized P0/P1 and P2 software scope and fixes confirmed defects found in that scope. It does not
certify every possible bug or full Optech parity. Production deployment, real-data reconciliation, physical printing
and operator/accountant/GST acceptance, including final Customer DC statutory wording, remain production activities.
Concurrent opening-balance/year-close/reversal UI/process-guide work from another chat is excluded from this checkpoint.

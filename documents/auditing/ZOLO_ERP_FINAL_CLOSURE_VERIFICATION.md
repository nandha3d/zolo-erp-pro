# Final closure verification, 2026-10-05

Software revision: `bf59c02ea98b89c070ad590aaa322f681ac84acd` on `main`.

**PHASES 1–12 SOFTWARE IMPLEMENTATION: ENGINEERING COMPLETE.** Browser verification and the final six-suite MySQL matrix **PASS**: [run 37314050856](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37314050856). External acceptance and production cutover remain **PENDING**.

## Worktree consolidation

The only remaining checkout is `V:/pers/Freelance/zolo-erp-pro`, on `main`. Each of the five removed phase worktrees was clean and had no commit absent from `main`. Git histories, branches and the existing stash were retained. Recoverable Git and ignored non-vendor files were backed up, with verification and checksum records, at `V:/pers/Freelance/zolo-erp-pro-worktree-backup-20261005-152501` before removal. No force push or customer database operation was used.

## Software closure changes

- Warehouse, cash-register, document, payment-parent and child reads now require company membership and authorized branch grants. Selected-branch writes remain enforced; warehouse transfers check both endpoints.
- `/clear` accepts authenticated, authorized, CSRF-protected POST requests and records actor/company audit evidence. Webview authentication accepts internal redirect paths only, including checks for nested encoding and backslashes.
- Ownership hardening covers tax, variant, discount, HR/payroll and operation child references. It validates the entire ownership graph before DDL, refuses ambiguous/conflicting data, supports replay and defers final constraints on empty installation until reviewed backfill.
- The shared query builder scopes joined and nested queries, unions and derived tables. Query compilation and execution use independent clones, preserving scoping when callers later append OR conditions or change company context. Partial model updates remain supported without permitting ownership changes.
- Notification, production, HR and supplier uploads use private storage and authorized delivery. Historical files require a live owner, permission, membership and relevant branch grants. Ambiguous ownership and traversal/symlink escapes fail closed. [Apache/Nginx guidance](../zolo_erp_implementation_docs/PRIVATE_FILE_DEPLOYMENT.md) documents direct-access denial.
- Browser findings were corrected in their responsible layers: mobile document totals and manufacturing grids, tablet POS navigation, table action positioning, selectpicker/Bootstrap compatibility, dark table totals, logo validation and POS null settings. Commercial dialogs restore usable focus. Purchase list labels and filters use recorded payment amounts because legacy and shared posting use different status codes.

## Browser and device evidence

The real in-app browser used the application's controllers, middleware, views, assets and MySQL against the explicitly isolated `zolo_test_completion_uat` database. Contact details were synthetic. Capability and activation adapters existed only in the ignored, loopback-only testing router; production activation defaults remain closed.

The [device matrix](closure-evidence-20261005/matrix-final.json) records **112 unique checks**: 28 screen/profile states at **1366×768, 1024×768, 768×1024 and 390×844**. Every recorded document width fits its viewport, and the final records contain no critical console errors. Wide tables use bounded horizontal scrolling. The matrix was accumulated during closure; affected screens and interactions were repeated after their final fixes.

Coverage includes dashboard, products, customers, suppliers, sale/purchase lists and creation, POS, fast sale/purchase, stock, transfer, adjustment, vouchers, GST, returns, manufacturing, job work, reports, company setup, roles and settings. General Trading and the selected FMCG, Textile, Timber and Solar profiles were checked at all four sizes. Profile forms reported successful configuration; General Trading was restored afterward.

Interaction checks:

- Keyboard customer/product selection and `Ctrl+Enter` posted `SAL-1-000001`, quantity 1, net price 999.99, tax 10%, total **1099.9890**.
- Mouse entry/draft restoration posted `PUR-1-000001`. Keyboard posting subsequently created `PUR-1-000002`; each purchase had quantity 1, net cost 899.99, tax 10%, total **989.9890**. [Posting confirmation](closure-evidence-20261005/purchase-keyboard-posted.jpg).
- A second sale with exhausted stock was correctly refused. The draft remained available; no successful second sale is claimed.
- `F2`, `F12`, `F6`, autocomplete, draft restoration and Escape were exercised. Closing the posting confirmation restored visible focus to product search.
- Sale actions opened without widening the page at desktop, tablet and phone sizes. Detail dialogs retained header/close controls and bounded item scrolling on phones. [Phone action menu](closure-evidence-20261005/sale-dropdown-390.jpg).
- Purchase payment selectpicker choices were exercised: Paid excluded both unpaid sample bills; Due returned both. Supported dark mode produced readable list rows and totals; light mode was restored.
- [Mobile totals](closure-evidence-20261005/sale-create-390.jpg) and [Solar profile](closure-evidence-20261005/profile-solar-390.jpg) provide representative layout evidence. Rendered purchase/layout and POS inline JavaScript compiled successfully; the changed external commercial script passed syntax validation.

## Automated and reconciliation evidence

The final run linked above independently passes company, commercial, compliance, operations, delivery and legacy-mysql for the software revision recorded here. The new purchase payment-filter regression also passes independently on SQLite and native MySQL: 1 test, 3 assertions.

| MySQL configuration | Tests | Assertions | Skipped |
| --- | ---: | ---: | ---: |
| company | 306 | 2981 | 0 |
| commercial | 29 | 152 | 1 |
| compliance | 63 | 364 | 0 |
| operations | 28 | 227 | 0 |
| delivery | 76 | 355 | 1 |
| legacy-mysql | 44 | 323 | 0 |
| Total | 546 | 4402 | 2 |

The skips are the opt-in commercial performance case and the SQLite-only archive case in the MySQL delivery run. The separate MySQL restore case runs. There are no failed tests.

PHP syntax checks passed for changed PHP/Blade files. JavaScript syntax and `git diff --check` passed. No unresolved Git conflict markers remain in the application, migrations, views, routes, tests, assets or CI configuration.

After the browser transactions, [synthetic health evidence](closure-evidence-20261005/synthetic-health.json) reports `ok: true`: stock quantities, accounting control balances and inventory value reconcile. This corrects the historical 21-difference repository-seed rehearsal at its source; it is not customer retained-data acceptance. Local native MySQL was 8.4.0; CI uses its configured MySQL 8.4.11 service.

## External acceptance

Customer retained-data reconciliation, accountant decisions, real provider/printer acceptance, private off-site recovery rehearsal, deployed Apache/Nginx direct-access checks, reviewer signatures and production cutover remain pending. Application tests and repository rules cannot prove the deployed virtual host's behavior. No capability activation, production release or statutory filing readiness is claimed.

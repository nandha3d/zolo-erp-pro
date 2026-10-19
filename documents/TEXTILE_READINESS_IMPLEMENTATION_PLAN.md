# Textile readiness implementation plan

Date: 2026-10-17 (Asia/Calcutta)

Branch: `ui`; inspected HEAD: `0c59c70`

Scope: implement the remaining P0/P1 Textile readiness requirements from the supplied Optech comparison, using shared ERP owners. This is the production-UAT baseline, not full Optech parity. Advanced receivables/collections, alternate item names, bulk item creation and quotation follow-up remain P2 unless explicitly promoted for day-one migration. The implementation and verification record below completes this plan; production rollout and human UAT remain separate gates.

Revision: incorporates the user's five corrections on shortcuts, the conditional Purchase Order gate, Customer DC purposes, sidebar consolidation, and physical movement/accounting/custody semantics. These decisions supersede conflicting historical notes.

## Baseline inspected before implementation

These historical findings describe source inspection on 2026-10-17, before implementation. The completion record at the end documents the resulting implementation and verification. Concurrent uncommitted work was preserved.

| Requirement | Current evidence | Planned treatment |
|---|---|---|
| Correct purchase-rate lookup | `CommercialController::previousRates()` filters both customer sales and supplier purchases with the same incoming party ID. Its summary queries also differ from the main history query in scope and reversal filtering. | P0 correctness fix in shared commercial history. |
| Full rate history | Endpoint already returns up to ten lines. `document-aids.js` displays only the first; the returned lines lack the complete operator-facing context. | Extend the existing entry aid and query, not a new module. |
| Party balance breakdown | Existing party endpoint reads open items and returns one outstanding amount. | Add previous/current/projected balance with settlement and edit semantics. |
| Purchase Orders | Status 4 already has separate numbering, partial receipts, quantity limits and `fulfilled_at`; `PurchaseOrderLifecycleTest` contains coverage. | Verify and close remaining lifecycle/UI gaps rather than replace the engine. |
| Sales Orders | `DocumentContextResolver` has no Sales Order reference; registry explicitly leaves Ctrl+F2 unassigned. Pending Sale is not an established Sales Order lifecycle. | Add an explicit order entity and fulfilment path; preserve Pending Sale. |
| Multiple DCs/GRNs per invoice/bill | Application boundary accepts one `delivery_challan_id` or `goods_received_note_id`. Uncommitted `MaterialDocumentService` already records per-line settlements, stock accruals and releases. | Extend this foundation to multiple sources with explicit allocation. |
| Commercial returns | Uncommitted `MaterialDocumentService::returnOpen()` and controller actions already implement unbilled returns. Tests also cover a sales credit note against a DC-backed invoice. | Verify, harden and complete purchasing and reversal coverage. |
| Customer DC purposes | `MaterialDocumentService::DC_PURPOSES` already supplies approval, quantity-unknown and not-supply options. | Add an explicit P1 purpose/validation gate; ordinary sales use invoices directly. Final purpose labels and any additional cases require accountant/GST UAT. |
| Dispatch navigation | Uncommitted sidebar work already groups DC, packing/challan and delivery under `Dispatch (DC, packing, delivery)`. | Verify consolidation, use the agreed name Dispatch Register, and remove standalone Supplier GRN/Customer DC sidebar entries. |
| Job Work returns/rework | `JobWorkService` flows and `JobWorkReturnsReworkTest` already cover unprocessed returns and rework. | Reuse; run focused regression during integration. |
| Bale/transport | Entry drawers and document metadata already carry bale and transport information. Sales register completeness and automatic bale defaults are not established. | Add defaults and profile-selected register columns. |
| Item negative stock | `products.allow_negative_stock` exists with a legacy default of true. `InventoryPolicy::negativeStock()` and the posting call currently use company policy only. | Introduce explicit inheritance without accidentally enabling negative stock for every legacy item. |
| Textile units/processes | Textile defaults currently seed MTR only. Catalog lacks KGS/DOZ and Bleaching. PCS already accepts Nos as an alias. | Extend shared unit catalog and configurable Textile presets. |
| Stock by lot | Lot selection and `batchBalances()` exist. The latter is tied to the batch-expiry capability and returns a capped list, not a complete Textile lot report. | Add a shared, scoped report independent of expiry control. |
| Document shortcuts | Shared registry exists. Inspected normal document scripts do not establish working Ctrl+B/Ctrl+S/Alt+Y actions. Existing Optech mapping documents their meanings. | Wire Ctrl+B = Pending Bills, Ctrl+S = Last Sale/previous sale history, Alt+Y = Party Statement. Keep Save/Post on the normal Save button or existing Ctrl+Enter submit action. |
| Item-group HSN | Product/GST assignment exists. Inspected `Category` model/controller has no group HSN default. | Extend the existing group/category master and GST assignment path. |

## Ownership and invariants

- Textile enables presets, labels, columns and field defaults. Commercial, Accounting, Inventory, Product/GST, and Job Work retain transaction ownership.
- Company, branch, financial year, permissions and warehouse access come from trusted context. Validate references again during posting, not only when opening a picker or draft.
- Orders create no stock movement, journal, open item or payment. A posted invoice, purchase receipt or material document owns each actual effect.
- For each source line, open quantity equals original base quantity minus active fulfilled/billed quantity minus active returned/cancelled quantity, as applicable. Reversal releases the corresponding allocation exactly once.
- Convert entered units to base units before comparing quantities. Preserve source line identity, product, variant, lot and warehouse. Matching product ID alone is insufficient.
- Lock source documents and lines in stable ID order inside the existing transaction boundary. Two concurrent conversions must not consume the same remaining quantity.
- A DC/GRN-backed bill settles already moved goods without moving those goods again. Price differences follow the existing inventory/COGS rules. Initially require the same party, company, branch and warehouse for combined sources.
- Supplier GRN posts the physical receipt immediately: debit Inventory, credit Goods Received Not Invoiced (GRNI). The Purchase Bill clears GRNI into AP and applicable tax accounting without receiving the same stock again; existing price-variance rules handle cost differences.
- Customer DC moves goods out of warehouse availability into identifiable customer/dispatch custody. For company-owned goods awaiting sale, debit Goods Delivered Not Invoiced (GDNI), credit warehouse Inventory; preserve source-line quantity/value in custody. This is an asset reclassification, not revenue, AR or COGS recognition.
- For invoiceable Customer DC goods, the later invoice recognizes revenue/AR/tax and COGS, clearing the corresponding GDNI asset without issuing warehouse stock twice. A return of unbilled goods restores warehouse stock and clears custody/GDNI. Other Than Supply movements do not automatically become sales.
- Custody is a distinct location/state with identifiable party, source document, lot, quantity and value. Reconcile warehouse plus company-owned customer custody to owned stock; exclude custody from warehouse available-to-sell quantity. Reuse existing settlements/movements/GDNI where they prove this contract rather than introducing a second stock engine.
- Store immutable source references for traceability in both directions. Existing singular source references remain readable; historical posted documents are not silently reposted.
- Retry keys return the original result for the same request and reject changed payloads. A failure rolls back numbering, allocation, stock, accounting and draft consumption together.
- Use existing server pricing, GST determination and rounding. History and balance projections assist entry; they never become client authority for posting.

## Delivery order

Deliver vertical slices in the order below. Each slice includes its targeted server tests; add browser checks only for changed operator behavior. Tasks marked M are intended as one focused session; split further if implementation expands beyond roughly five production files. Test files are additional verification artifacts.

### Phase A — correctness and a verified foundation

**1. Correct rate-history identity and scope — P0, M**

Owner/files: `CommercialController.php`, a focused query in `app/Services/Commercial`, and `CommercialPermission.php` only if required by existing permission semantics. Dependencies: none.

Acceptance:
- On Sales, previous sale rates use the selected customer and product; purchase cost uses the product's permitted supplier purchase history, never the customer ID. On Purchases, history uses the selected supplier and product.
- Detail and summary apply the same visible branch/company scope, effective business-date ordering, posted/unreversed filters and deterministic tie-breaker. Drafts, orders and reversed documents are excluded.
- Purchase cost is exposed only under an explicit cost-view permission or the fitting existing purchasing permission. Missing history returns a clear empty value, never an unrelated rate.

Verification: add regression fixtures where customer and unrelated supplier share a numeric ID; cover branch/company leakage, reversals, backdated documents and denied cost access.

**2. Verify material-document and return foundations — P0, M**

Owner/files: existing `MaterialDocumentService.php`, material-document migration and their focused test fixtures. Dependencies: none. Preserve the current uncommitted implementation.

Acceptance:
- Supplier GRN immediately receives stock and credits GRNI; Purchase Bill clears GRNI into AP/tax without another receipt. Customer DC transfers warehouse stock to customer/dispatch custody and GDNI without revenue/AR/COGS; invoice recognizes the sale and clears GDNI without another warehouse issue.
- Document which tests prove current behavior and which gaps remain, including feature-disabled and pre-migration documents.
- Custody quantities/value, unbilled returns, single-source settlement and reversal reconcile on a disposable migrated database. Migration rehearsal does not double-post historical material documents or change existing invoice quantities/accounting without an explicit reconciliation path.

Verification: run `MaterialDocumentPostingTest`, `MaterialDocumentReturnsTest`, and relevant conversion tests sequentially. Assert warehouse availability, customer custody and GRNI/GDNI balances before and after billing/return/reversal, including zero revenue/AR/COGS at DC dispatch. Review migration reconciliation before extending settlement.

**2a. Restrict commercial Customer DC purposes — P1, M**

Owner/files: `MaterialDocumentService.php`, `ValidatesMaterialDocuments.php`, `DeliveryChallanController.php`, Customer DC form/print purpose partial. Dependencies: task 2; reuse the existing purpose catalog and validation instead of duplicating them.

Acceptance:
- Every commercial Customer DC requires an explicit allowed purpose, including On Approval / Sale or Return or Other Than Supply. Normal sales use an invoice directly; a generic DC must not bypass that flow.
- Validate purpose on the server for create/edit/draft restore and before posting/conversion. Preserve the selected purpose on the document and print; block unsupported purpose-to-invoice conversion. Review the current quantity-unknown option explicitly rather than treating its presence in code as approval.
- Keep commercial Customer DC distinct from Job Work Material DC. Final statutory wording, permitted cases and conversion treatment are confirmed during accountant/GST UAT before production acceptance.

Verification: focused tests for missing/unknown purposes, allowed movements, normal-sale misuse, stale drafts and non-supply conversion refusal; browser purpose selection, error recovery and printed purpose. This is a workflow requirement, not a claim that current labels establish statutory compliance.

**2b. Consolidate Dispatch Register and workspace actions — P1, M**

Owner/files: `backend/layout/sidebar.blade.php`, `WorkspaceNavigation.php`, Dispatch Register view/controller and existing workspace navigation partial. Dependencies: task 2; task 2a supplies purpose-aware Customer DC actions.

Acceptance:
- Remove standalone Supplier GRN and Customer DC sidebar entries. Merge Challan List, Customer DC and Delivery List into one sales-side **Dispatch Register**, retaining existing packing/challan/delivery records and actions in its views/tabs.
- Keep Supplier GRN actions inside the normal Purchase workspace and Customer DC actions inside the normal Sales workspace. The Dispatch Register is the consolidated sales listing, not another transaction engine; Job Work retains its own material actions.
- Preserve permission filtering, active navigation state, valid legacy links/redirects and existing document access. A user permitted only one dispatch type can reach its authorized tab without requiring unrelated DC permissions.

Verification: focused navigation/permission tests and browser sidebar, each register tab, legacy links and Sales/Purchase GRN/DC actions. Reuse the uncommitted consolidation and rename its label where needed.

**Checkpoint A:** Release blocker is closed only when task 1 regressions pass. Tasks 2/2a/2b establish physical movement, purpose and navigation acceptance before multi-source work; source presence alone is not acceptance.

### Phase B — commercial document lifecycle

**3. Complete Purchase Order fulfilment rules — P1, M**

Owner/files: `CommercialApplicationService.php`, `DocumentContextResolver.php`, `CommercialReversalService.php`. Dependencies: task 2 for material-receipt integration.

Acceptance:
- Existing status-4 orders retain their series and no-posting behavior; line quantities remain distinct for repeated products, variants, lots and units.
- Partial/full receipt, cancellation of remaining quantity, receipt reversal and replacement calculate correct remaining quantities and reopen/close the order consistently.
- Direct purchase receipts and GRN-backed receipts cannot fulfil the same order quantity twice; concurrent requests cannot over-receive.

Verification: extend `PurchaseOrderLifecycleTest` with reversal, cancellation, duplicate product lines, mixed units and concurrency cases.

**4. Expose pending Purchase Orders and load action — P1, M**

Owner/files: `PurchaseController.php`, purchase entry view, `document-workspace.js`, `command-center-form.js`. Dependencies: task 3.

Acceptance:
- Register shows ordered, fulfilled and pending quantities with open/partial/fulfilled/cancelled states.
- Loading an order fills only remaining quantity; draft resume revalidates the order before posting.
- Ctrl+F12 remains unassigned until all nine Purchase Order lifecycle checks below pass. Then wire it through the shared registry to the existing New Purchase Order action; this is a conditional acceptance gate, not a permanent prohibition.

Verification: targeted context/draft tests and browser partial receipt, reload, completion, stale-draft refusal and the conditional Ctrl+F12 enablement gate.

#### Purchase Order lifecycle: nine checks before Ctrl+F12

Record evidence for every check against the implementation revision being enabled. An absent or failed check keeps `documents.purchase_order_shortcut` off and omits the shortcut from help/registration. Once all nine pass, enable the gate and add a real-browser Ctrl+F12 proof; a configuration flag alone is not evidence.

1. **Distinct numbering:** Purchase Orders draw only from the company/branch/FY order series and do not consume purchase bill numbers.
2. **No premature effects:** Saving/confirming an order creates no stock, journal, open item or payment; attempted payment is rejected.
3. **Trusted source context:** Only an active authorized order for the same company/branch, supplier and warehouse can be loaded/received; validate FY/date under normal posting rules and reject foreign or unusable references.
4. **Load remaining lines:** Loading/restoring uses remaining base quantities and original order-line/UOM/variant/lot identities, including repeated product lines; stale references fail without losing the draft.
5. **Partial receipt:** A partial receipt reduces remaining quantity correctly and keeps the order open; direct receipt and GRN-backed receipt do not count the same goods twice.
6. **Full receipt:** Receiving the final remaining quantity marks the order fulfilled/closed and prevents further fulfilment.
7. **Over-receipt and concurrency:** Excess quantity is rejected; concurrent receipts and retry keys cannot allocate the same remainder twice or leave partial effects after failure.
8. **Cancellation/edit restrictions:** Cancel only the unfulfilled remainder, retain fulfilled history, block later conversion of cancelled quantities and prevent edits below fulfilled quantity.
9. **Reversal/replacement recovery:** Receipt or linked bill reversal releases only its applicable fulfilment once, reopens the correct remainder and preserves source history; replacement/retry cannot duplicate effects.

**5. Create explicit Sales Orders — P0/P1, M**

Owner/files: additive order migration, `SalesOrder` and line model, one commercial order application service, focused controller. Dependencies: task 3's settled quantity conventions.

Acceptance:
- Recommended storage is dedicated sales-order headers/lines with company, branch, financial year, customer, warehouse, date, series and explicit lifecycle state. Preserve existing Pending Sales unchanged.
- Create/edit/cancel use normal authorization, numbering, audit and retry boundaries. Confirmation creates no inventory/accounting/payment effects.
- Once fulfilment exists, edits cannot reduce ordered quantity below fulfilled quantity or rewrite fulfilled line identity. Cancel only the unfulfilled remainder.

Verification: lifecycle tests for no side effects, ownership, duplicate requests, cancellation and post-fulfilment edit restrictions. Reuse existing numbering/guard services; do not create a parallel billing engine.

**6. Allocate Sales Order fulfilment — P0/P1, M**

Owner/files: order allocation migration, order service from task 5, `CommercialApplicationService.php`, `CommercialReversalService.php`, `DocumentContextResolver.php`. Dependencies: task 5.

Acceptance:
- Posted invoices allocate explicit order lines in base units, support partial/full billing, and close the order only when its remainder reaches zero or is explicitly cancelled.
- Reversal releases allocations once; failed posts/retries/concurrent invoices never lose or double-consume remaining quantity.
- Orders routed through Customer DCs preserve the order-line reference. DC delivery and invoice billing remain distinct measures and do not count as two fulfilments.

Verification: new Sales Order lifecycle tests for partial/full billing, cancellation, reversal/replacement, repeated product lines and concurrent conversion. Share a quantity helper with purchasing only where both implementations actually require it.

**7. Expose Sales Orders in normal sales workspace — P1, M**

Owner/files: sales controller/view, `document-workspace.js`, `command-center-form.js`, draft service/context support as needed. Dependencies: task 6; split context/draft plumbing into a separate slice if needed.

Acceptance:
- Operators create an order, browse pending quantities and load remaining lines into the existing Sales Bill form.
- Drafts preserve and revalidate order/line references; server refuses stale/foreign allocations without discarding draft work.
- UI distinguishes Pending Sale from Sales Order. Do not assign Ctrl+F2 merely because an order screen exists; expose it only after lifecycle acceptance and shortcut review.

Verification: focused draft tests and browser create order, partially bill, reload, fully bill, trace source and reverse invoice.

**Checkpoint B:** Demonstrate ordered quantity 30, invoices 10 and 20, remainder 0; reverse the second invoice and verify remainder 20. Repeat the receipt equivalent for purchasing. Record all nine Purchase Order checks before enabling Ctrl+F12, then prove the shortcut opens a new order in the normal workspace.

**8. Accept multiple material sources atomically — P1, M**

Owner/files: `CommercialApplicationService.php`, `MaterialDocumentService.php`, request/legacy command adapter and an additive allocation migration only if the existing settlement schema needs it. Dependencies: tasks 2 and 2a.

Acceptance:
- Accept a bounded list of source documents and explicit source-item allocations; normalize the existing singular source field for compatibility and reject conflicting representations.
- Enforce same party/company/branch/warehouse, document eligibility, remaining quantity, lot/variant/UOM identity and stable source locking.
- Existing posting/reversal services settle all sources atomically with no duplicate stock movement. Do not loop the current single-source allocator unchanged: it resets each bill line's remaining quantity on each call.

Verification: two/three sources with repeated products and different lots, partial allocation, source returns, changed-payload retries, over-billing, concurrent consumers and rollback after a late posting failure.

**9. Add multi-DC/GRN selection and traceability — P1, M**

Owner/files: material document controller/read endpoints, `material-document.js`, normal sales/purchase source picker partial, document rendering reference builder. Dependencies: task 8. Split selling and purchasing UI delivery if this exceeds one session.

Acceptance:
- Operators select pending Customer DCs or Supplier GRNs for one party, review open quantities and load them into one normal invoice/bill.
- Preserve source-item allocations through draft save/resume. Show source references on bill detail/print and linked bills on source detail, including partial and reversed history.
- Unselected excess bill quantity follows an explicit direct-stock path already supported by posting; it is never silently allocated to another source or lot.

Verification: browser select DC quantities 10 + 8 + 12, invoice 30, inspect both trace directions, reverse, then repeat with three GRNs. Server tests remain the authority for stock and accounting totals.

**10. Complete contextual material returns — P1, M**

Owner/files: `MaterialDocumentService.php`, `ReturnService.php`, shared return action partial/script; extend existing controller actions instead of new modules. Dependencies: tasks 2 and 8.

Acceptance:
- Customer DC Return and Supplier GRN Return act only on unbilled remaining quantities; billed goods use the invoice/bill return path. Both restore/release original source identities and accrued value correctly.
- A duplicate return request has one effect; excess, foreign, earlier-date and closed-period requests fail atomically. Expose partial return and history in source detail.
- Existing Job Work Return Material and Return/Send for Rework stay under Job Work, preserving conversion, loss, service-bill and reversal restrictions.

Verification: extend current material return tests for the purchase side, partial returns plus billing, repeated requests, reversals and lot identity. Run `JobWorkReturnsReworkTest` as the focused compatibility gate; browser smoke the four contextual actions.

**Checkpoint C:** Reconcile warehouse quantity/value, company-owned customer custody and GRNI/GDNI before material documents, after documents, after combined billing, after partial returns and after reversal. Prove no revenue/AR/COGS at Customer DC dispatch, no second stock receipt/issue at billing, and both directions of source traceability.

### Phase C — shared inventory and product defaults

**11. Honor per-item negative-stock policy safely — P1, M**

Owner/files: `InventoryPolicy.php`, `InventoryMovementService.php`, product policy migration, product write validation and existing product fields partial. Dependencies: none.

Acceptance:
- Resolve an explicit item policy before company policy, with company/legacy fallback when no override exists. Recommended field semantics: inherit/allow/warn/block, represented by nullable override plus existing company choices.
- Legacy `allow_negative_stock=true` was the schema default while ignored. Do not reinterpret every historical true as intentional permission. Preserve previous effective company behavior; map only proven explicit choices and document the migration.
- Central stock posting applies the policy to sales, DCs, supplier returns, transfers, job work and adjustments. Keep batch/serial/identity restrictions intact; a negative-stock override does not waive them.

Verification: targeted policy/posting tests for inheritance, explicit allow/block/warn, separate products in one document, company isolation, cached policy invalidation and rollback/concurrency.

**12. Seed common units without duplicate identities — P1, M**

Owner/files: `IndustryCatalog.php`, `IndustryProfileService.php`, replay-safe preset migration; existing UOM conversion owner only if required. Dependencies: none.

Acceptance:
- Textile companies receive MTR, KGS, DOZ and PCS/Nos choices using canonical shared units; reuse existing aliases and preserve customized units.
- Nos remains a supported label/alias for PCS unless a real distinct identity is required. DOZ equals 12 PCS only for a compatible count product; no global weight/length/count conversion is inferred.
- Installation/backfill is repeatable, company-scoped and does not change quantities or existing unit factors on posted documents.

Verification: industry preset tests for new/existing companies, alias matches and replay; UOM tests for valid dozen conversion and incompatible dimensions.

**13. Add configurable Bleaching preset — P1, S**

Owner/files: `IndustryCatalog.php`, replay-safe Textile preset migration. Dependencies: none; serialize migration/default edits with task 12.

Acceptance:
- Bleaching appears beside existing Dyeing/Printing/Finishing processes for new and existing Textile companies.
- Preserve edited process policies; choose a conservative configurable default and require operators to configure actual conversion/output/loss policy before use. Do not invent a textile loss allowance.
- Switching profiles follows existing preset activation rules without changing prior job-work documents.

Verification: extend industry preset/process tests, including a customized Bleaching process and repeated installation.

**14. Inherit item-group HSN on creation — P1, M**

Owner/files: category migration/model/controller/form and shared product/GST assignment owner. Dependencies: none; separate normal/quick product adapter changes if the file scope grows.

Acceptance:
- Use the existing category/group master, adding a validated default HSN. Do not introduce another group master.
- Normal and quick item creation use explicit item HSN first, then direct group default; missing defaults stay unset and follow current posting validation. Do not add ancestor traversal without a demonstrated requirement.
- A group edit never rewrites existing product HSN or posted tax snapshots automatically. Reuse current GST validation and goods/service code rules.

Verification: focused product/GST tests for explicit override, inherited/missing defaults, quick creation, foreign group and unchanged historical snapshots.

**15. Deliver actual stock-by-lot report — P1, M**

Owner/files: `IndustryStockQueries.php` or existing inventory report query owner, operations/report controller, route, report view, report navigation/preset. Dependencies: task 2; validate again after task 10.

Acceptance:
- Show product, lot, warehouse/godown and base unit with opening/inward/outward/closing quantities for the requested dates; filter by product/lot/warehouse and include unbatched stock explicitly.
- Use the authoritative applied stock ledger and authorized company/branch warehouses. Paginate/export the full filtered result rather than silently stopping at 200/500 rows. Textile does not require batch-expiry capability to view lots.
- Reconcile totals against shared stock balances after job-work conversion, material returns and invoice reversal. Show warehouse stock and company-owned customer/dispatch custody separately, with an owned-stock total that does not double-count either location. Keep weighted-average item valuation; do not imply independent lot costing.

Verification: inventory report fixtures with multiple branches/lots, nullable expiry, reversals, date boundaries and pagination; browser filters, totals and export smoke.

**Checkpoint D:** Fresh and upgraded Textile companies receive valid defaults, General Trading/FMCG retain their behavior, and lot totals reconcile with shared inventory.

### Phase D — billing aids and operator completion

**16. Expose full history and optional margin — P1, M**

Owner/files: query from task 1, `document-aids.js`, existing entry-aid dialog/style partial. Dependencies: task 1.

Acceptance:
- Show dated previous sales/purchases with document, party, quantity, unit and normalized net rate; offer bounded pagination and click-to-apply on the active line.
- Show permitted last purchase cost separately. Show margin amount/percentage only when unit, currency and tax basis are comparable and cost is positive; otherwise explain why margin is unavailable.
- Late responses after changing party/item/tab cannot update the wrong row. Empty/error/loading states and keyboard dialog/focus behavior work.

Verification: query tests plus browser change party rapidly, open history, apply a rate, switch drafts and check denied cost access.

**17. Show previous/current/total party balance — P1, M**

Owner/files: current commercial party endpoint/accounting read owner, `document-aids.js`, document amount/settlement events. Dependencies: none; integrate after task 16 to avoid overlapping aid edits.

Acceptance:
- Define Previous as posted outstanding excluding the document being replaced; Current as the current document's net open-item effect after immediate settlement; Total as the projected result. Label receivable/payable and credit signs explicitly.
- Existing posted bills, replacements, returns, deposits and fully paid documents do not double-count. Unsupported settlement projections are labelled unavailable rather than guessed.
- Refresh on party, line, charge, tax, payment and draft changes; stale requests cannot overwrite the selected party's balance. Posting still rechecks credit limits independently.

Verification: accounting/entry tests for unpaid, partial, cash, credit balance and replacement cases; browser edit total/payment, switch party and reopen a posted bill.

**18. Default Bale No and expose register columns — P1, M**

Owner/files: existing transport/default owner, sales register query/view, Textile column preset and document draft adapter only where needed. Dependencies: none.

Acceptance:
- For Textile, use the final allocated document reference when Bale No is empty; preserve every explicit override. Draft previews must not consume a bill number or promise an unstable reference.
- Sales register includes selectable Bale No and Transporter columns, enabled by Textile preset, backed by stored metadata and included in the matching export.
- Reset/new draft, copy, edit and replacement follow explicit rules: new/copy derive a new default; edit preserves stored value; replacement preserves an explicit value and derives only when blank.

Verification: reference/transport tests and browser register, column visibility, export, override, copy and draft restore.

**19. Wire documented document shortcuts — P1, M**

Owner/files: `DocumentShortcutRegistry.php`, `document-shortcuts.js`, document workspace action adapter/help and `DOCUMENT_ENTRY_CONSOLIDATION.md`. Dependencies: tasks 16/17 for history/balance actions and task 4 for the conditional Purchase Order shortcut; basic actions can ship independently of Sales Orders.

Acceptance:
- Apply the established Optech mapping: **Ctrl+B = Pending Bills; Ctrl+S = Last Sale/previous sale history; Alt+Y = Party Statement**. On Sales, Ctrl+S uses the selected customer and active product; purchase entry may retain its contextual previous-purchase history action with a clear label. Party Statement must show the statement, not merely alias the Pending Bills dialog. Keep Save/Post on the normal Save button or existing **Ctrl+Enter** submit action; Ctrl+S never saves/posts.
- One action registration owns each shortcut/help entry. Only prevent browser defaults when that enabled action can execute; respect active dialog/IME/control ownership, validation and permission boundaries.
- Repeated Save/Ctrl+Enter during an in-flight request does not duplicate a post; failed Save retains draft. Preserve F2/F12/F6 behavior. Keep Ctrl+F12 disabled until all nine Purchase Order checks pass, then enable and document it with browser proof.

Verification: focused keyboard tests and real browser checks that Ctrl+B opens Pending Bills, Ctrl+S opens history without submitting, Alt+Y opens the selected Party Statement, and Save/Ctrl+Enter uses the existing post path. Test modal suppression, missing context, denied permissions, draft preservation, repeated keys and Ctrl+F12 before/after its nine-check gate.

**Checkpoint E:** Complete a Textile order, purpose-restricted dispatch, combined billing, return, balance review, rate selection, lot lookup and save in the existing workspace. Verify the consolidated Dispatch Register and the exact Ctrl+B/Ctrl+S/Alt+Y mappings, plus Save/Ctrl+Enter and conditionally enabled Ctrl+F12.

## P2 backlog

These items do not block the agreed P0/P1 baseline unless the migration customer explicitly requires them for day one. Each should receive its own implementation slice and acceptance tests before development.

1. **Receivables report:** extend Accounting with configurable ageing buckets, party/invoice/due date/days overdue, agent, area, salesman and ledger group. Use as-of open-item data; reconcile totals to receivables before adding collection actions.
2. **Collections:** separate deliveries for follow-up date/notes, PDC recording, interest preview, cash-discount simulation and reminders. Decide financial calculation policy before posting any interest; reminders require explicit messaging authorization.
3. **Alternate item names:** company-owned product aliases with optional party scope, deterministic search priority and ambiguity handling; aliases never replace stable product identity.
4. **Bulk item creation:** shared master import with preview, row validation, company scope, duplicate detection and explicit partial/all-or-nothing behavior; reuse current product/GST/UOM writers.
5. **Quotation next follow-up:** add date and list filter to the existing quotation workflow; converted quotations retain history. External notifications are a separate opt-in action.

## Validation and migration strategy

- Before execution, record the current diff and identify the owner of overlapping uncommitted changes. Do not reset, stash, commit or overwrite unrelated work. Re-read the affected source before each slice.
- Use additive migrations and company-scoped replay-safe backfills. Run a fresh-install and existing-data rehearsal on a disposable database. Snapshot before any real migration; never run accounting/stock backfills against live data as part of a test.
- Order/source allocation migrations need indexes and uniqueness appropriate to company/source line/consumer identity. Stable locks plus database constraints enforce concurrent correctness. Never reuse an existing migration number.
- Rollback before posting may revert a schema change only when no dependent records exist. After posting, disable the new entry action and use audited business reversals/corrections; do not drop allocation or return history.
- Extend existing isolated PHP fixtures. Run only the relevant test class/filter during each slice; run suites sequentially because shared test databases are not safe for simultaneous runs.
- Existing baseline commands include `php vendor/bin/phpunit -c phpunit.commercial.xml --filter PurchaseOrderLifecycleTest`, `php vendor/bin/phpunit -c phpunit.commercial.xml --filter MaterialDocumentPostingTest`, and `php vendor/bin/phpunit -c phpunit.operations.xml --filter JobWorkReturnsReworkTest`. Confirm each additional test is included in the appropriate suite before relying on a suite command.
- Browser checks apply only to changed UI flows. Existing document-aids browser checks use mocked read responses, so extend verification with real history/balance endpoints. Existing draft smoke tests may accept posting refusal; readiness needs a configured disposable company with successful stock/accounting posting.
- At integration, run affected commercial, inventory, operations, GST and document suites, then one successful end-to-end Textile scenario. Record exact commands, results, database scope and any pre-existing failures. No broad performance/build check is required for a documentation-only plan.

## Decisions to settle before dependent implementation

The following do not prevent beginning task 1 or foundation verification:

- Shortcut meanings and the Ctrl+F12 gate are settled, not open questions: Ctrl+B = Pending Bills, Ctrl+S = Last Sale/previous sale history, Alt+Y = Party Statement; Save/Post uses the normal Save button or existing Ctrl+Enter. Enable Ctrl+F12 only after the nine Purchase Order lifecycle checks pass.
- Confirm final Customer DC statutory purpose labels and permitted conversion cases during accountant/GST UAT. The required workflow is already settled: purpose-restricted commercial DC, invoice directly for ordinary sales, and separate Job Work material actions.
- Confirm that the existing category master is the intended operator-facing item group. Proposed default: use that master without adding another hierarchy.
- Confirm the negative-stock migration mapping if evidence of deliberately edited legacy booleans exists. Proposed default: preserve prior effective company behavior and require an explicit new override.
- Confirm whether day-one combined invoices must span warehouses. Proposed baseline rejects cross-warehouse combinations because the current bill model has a single warehouse.
- Confirm any required sales-order delivery promises, multi-currency history or P2 migration requirements before expanding those contracts. Baseline uses current company currency and preserves separate delivered/billed quantities.

## Completion gate

Textile is ready for production UAT only when every P0/P1 task, including 2a/2b, passes its acceptance checks, scoped source references and warehouse/custody/accrual balances reconcile, and no rate/history or inventory/accounting correctness issue remains unresolved. This certifies the agreed P0/P1 baseline, not full Optech parity; the listed P2 backlog remains outside that gate unless explicitly promoted. Record a completed checklist with evidence, including all nine checks before enabling Ctrl+F12, rather than equating seeded profile flags or visible screens with finished functionality. Physical printing, real-data reconciliation and operator/accountant sign-off remain separate production gates; final Customer DC statutory wording is confirmed during that UAT.

## Implementation completion record — 2026-10-19

All 21 P0/P1 slices (tasks 1–19 plus 2a/2b) are implemented on `ui`. This record describes the current uncommitted workspace, including the preserved material-document foundation. It does not claim deployment, full Optech parity, physical printer acceptance or accountant sign-off.

### Completed scope

- [x] **1:** Shared scoped rate history fixes customer/supplier identity, reversal filtering, business-date ordering and cost permissions. `RateHistoryTest` covers identity, scope, pagination and frozen conversion/currency context.
- [x] **2:** GRN receives physical stock against GRNI; DC transfers warehouse stock to GDNI/customer custody. Bills settle those movements without a second receipt/issue. Material posting, identity, returns and ledger proof cover allocation, valuation, cancellation and reversal.
- [x] **2a:** Customer DC requires an explicit permitted purpose, with ordinary sales directed to invoicing. Statutory labels remain subject to accountant/GST UAT.
- [x] **2b:** Dispatch Register combines Customer DC, challan/packing and delivery references. Standalone GRN/DC sidebar entries are removed; normal workspace actions remain. Permission and ownership filters are covered by `DispatchRegisterTest` and browser checks.
- [x] **3–4:** Purchase Orders use distinct numbering, frozen source lines, remaining quantities, partial/full receipt, amendments, cancellation and reversal. The normal Purchase workspace exposes remaining-line loading and stale-draft refusal. The nine-check evidence follows below.
- [x] **5–7:** Dedicated Sales Orders share the commercial line allocation owner. Pending Sale retains its previous meaning. The normal Sales workspace creates, loads and bills orders; DC delivery and invoice billing are separate measures. Browser proof covers 30 ordered, 10 plus 20 invoiced, then reversal reopening 20.
- [x] **8–9:** Multi-GRN/DC bills carry explicit source-item allocations with scoped party/warehouse/variant/lot identity, stable locking, immutable references, draft restoration, backlinks and printed references. Real browser checks combine three sources on each side.
- [x] **10:** Contextual partial returns operate on exact open source lines. Invoice returns cap each source chunk at its actual settlement, subtract prior returns and preserve cost. Same-product/same-lot sources at different costs and repeated returns have regression coverage.
- [x] **11:** Nullable item policy supports inherit/allow/warn/block without reinterpreting the legacy default-true boolean as an explicit override. Existing company behavior remains the inherited default.
- [x] **12–13:** Shared defaults add MTR/KGS/DOZ/PCS with Nos alias and an inactive, configurable Bleaching process. Replay and profile tests cover existing and newly configured companies.
- [x] **14:** The existing Category master supplies group HSN defaults through normal and quick product creation. Explicit product HSN wins; editing a group does not rewrite existing products or posted tax snapshots.
- [x] **15:** Stock by lot reads the applied ledger with date/product/lot/warehouse filters, explicit unbatched stock and full filtered export. Period warehouse movement and current customer custody/owned stock are labelled separately. Browser filters and CSV reconcile to the fixture ledger.
- [x] **16–17:** Full history supports normalized rate application and permitted cost/margin context. Party assistance distinguishes previous/current/projected balance, Pending Bills and dated Party Statement. Requests reject stale context; posting remains server-authoritative.
- [x] **18:** Empty Textile Bale No uses the final allocated reference; overrides and Transporter persist through edit and register/export. Copy/new clear the old derived value. Browser and `TextileEntryContractTest` cover these rules.
- [x] **19:** Ctrl+B opens Pending Bills, Ctrl+S opens previous rates, Alt+Y opens Party Statement, and Ctrl+Enter follows normal Save/Post. Repeated submission does not duplicate effects. Ctrl+F12 is enabled after the completed gate and omitted when disabled or allocation schema is absent. Ctrl+F2 remains unassigned pending a separate shortcut review.

### Purchase Order gate evidence

| Check | Result and evidence |
|---|---|
| 1. Distinct numbering | Passed: `PurchaseOrderLifecycleTest`; browser order creation preserves the purchase bill series. |
| 2. No premature effects | Passed: lifecycle tests and browser verify no stock/journal/open item/payment on order save; attempted payment fails. |
| 3. Trusted source context | Passed: lifecycle, boundary and allocation tests reject foreign, inactive, mismatched party/warehouse and unusable references. |
| 4. Remaining lines and drafts | Passed: allocation identity tests and browser preserve exact line references; cancelled-order draft is retained but client and server refuse posting. |
| 5. Partial receipt | Passed: direct, later and GRN-backed receipt tests track delivery separately from billing. Browser receives 10 of 30. |
| 6. Full receipt | Passed: final 20 closes the browser order; further allocation is rejected by lifecycle tests. |
| 7. Excess, races and retries | Passed: excess/retry tests and six real MySQL process races prove one remaining quantity cannot post twice. |
| 8. Cancel/edit restrictions | Passed: fulfilled identity/quantity guards, versioned amendments and browser cancellation of only the reopened 20. |
| 9. Reversal/replacement | Passed: allocation and material tests release the exact applicable stage once; browser reversal reopens 20 while preserving the earlier 10. |

After these checks, `documents.purchase_order_shortcut` defaults to true. The registry also requires shared Commercial and installed order item/allocation/event tables. Browser proof covers disabled and enabled configurations and repeat-key draft preservation.

### Verification evidence

Tests ran sequentially against isolated fixtures. Browser and upgrade rehearsal used disposable database `zolo_test_textile_uat_20261018`; process races used only `zolo_test_textile_concurrency`. The configured development database was not used for this final rehearsal. Additive migrations 000007–000011 were rehearsed on the existing-data clone; isolated feature fixtures exercise fresh schema and replay behavior. No full legacy installer certification is implied.

| Verification | Recorded result |
|---|---|
| `php vendor/phpunit/phpunit/phpunit -c phpunit.commercial.xml` | 128 tests, 769 assertions, 7 skips. Six require the separately executed MySQL configuration; one is the existing retired-capability skip. |
| Fractional partial-fulfilment regression added after that full run | 1 test, 2 assertions passed. It proves unchanged conversion does not fail because a partial quantity rounds differently. |
| Final `OrderAllocationLifecycleTest` run after the rounding fix | 19 tests, 70 assertions passed, including the fractional regression and existing allocation guards. |
| Commercial MySQL concurrency class with dedicated fixture environment | 6 tests, 138 assertions passed after the final allocation/receipt fixes. |
| Compliance filter: `MaterialReturnAllocationTest\|MaterialDocumentReturnsTest\|GstSetupTest\|ReturnsDocumentsTest` | 24 tests, 159 assertions passed. |
| Operations filter: `TextileInventoryDefaultsTest\|JobWorkReturnsReworkTest\|IndustryProfilesTest` | 20 tests, 173 assertions passed. |
| Document web filter: `DocumentEntryWebTest\|OptechMasterWebTest` | 27 tests, 184 assertions passed. |
| JavaScript suite (`node --test tests/JavaScript/*.cjs`) | 16 passed in the final run. |
| Independent specification and code review | All six identity/valuation/return findings resolved; final fractional-conversion finding fixed and reviewed. No outstanding findings in those reviewed changes. |

Real-browser evidence in ignored `scratch/` includes `textile-flow.mjs` (9 checks), `textile-orders-browser.mjs` (17), `textile-final-ui.mjs` (9), `textile-draft-browser.mjs` (9), and `textile-material-browser.mjs` (38), plus inventory/default/export checks. All recorded runs have no page JavaScript errors. `textile-mobile-proof.mjs` verifies actual wheel scrolling reaches Save on Sales and Purchases at 390×844; a scoped commercial CSS fix resolves the shared-shell overflow conflict. Desktop material, order and assistance flows were verified at 1440px.

`textile-material-ledger-proof.json` independently confirms the three-source purchase and sale create zero additional physical movement lines. GRNs reconcile 30 original = 30 billed. After invoice reversal and a partial DC return, DCs reconcile 30 original = 2 returned + 28 open. Printed A4 invoice previews include all three source references; physical printer output remains a UAT activity.

One unrelated existing documents-web failure remains: `NavigationWebTest` expects 404 for `/unit/create` but receives 403. Its menu-link test passes. No change in this implementation attempts to alter that permission/routing contract.

### Rollout boundary

Apply the additive migrations through the normal backed-up deployment process before enabling new entry actions on real data. Do not drop allocation, settlement or return history after posting. The existing-data rehearsal does not authorize live accounting backfills. Complete real-data reconciliation, physical printing and operator/accountant/GST UAT, including final DC statutory wording, before production sign-off. P2 remains deferred exactly as listed above.

## P2 promotion — 2026-10-19

The user subsequently authorized all P2 slices after local migration and the P0/P1 push (`4767352`).
The earlier deferral and navigation-failure note above describe the P0/P1 checkpoint only. P2 implementation,
its corrected route expectations, final verification and local migration evidence are recorded in
[TEXTILE_P2_IMPLEMENTATION.md](TEXTILE_P2_IMPLEMENTATION.md). Production reconciliation, physical printing and
operator/accountant/GST sign-off remain separate gates.

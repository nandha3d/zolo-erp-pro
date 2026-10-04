# Phase 4c — Legacy authoritative cutover

Implementation package dated 2026-10-04. Scope follows Phase 4c in [the execution plan](../ZOLO_ERP_EXECUTION_PLAN.md). This package converts the remaining operational writers; Sale, Purchase, Return and ReturnPurchase controllers still require the separate Phase 4b commercial cutover.

## Delivered behavior

- Adjustment store/update/delete and bulk delete use signed movements. An update reverses the previous movement and replaces persisted lines instead of accumulating quantities or applying the wrong variant sign.
- Transfer store/update/import/delete/bulk delete and pending completion use the movement service. Completed transfers balance both warehouses. Pending transfers have no stock effect. In-transit transfers issue from the source and reduce the aggregate on-hand projection consistently; editing to Completed reverses the issue and posts the transfer. Pending completion locks the document and cannot post twice.
- Packing slips issue standard products or combo components, preserving UOM, variant, batch and serial identities. Packing the same sale line twice is rejected. Deleting a new slip reverses its recorded components and costs even if the current combo recipe changes. Other slips and their delivery references are retained.
- Production consumes ingredients before receiving output, in one transaction. Deletion reverses actual movements rather than reconstructing consumption from today's product recipe. The unrouted `storebackup` stock writer is removed; errors roll back and are reported instead of calling `dd`.
- Damage and exchange use applied issues/receipts. Missing warehouse rows follow the configured negative-stock policy. An exchange whose outgoing issue fails rolls back its returned stock and document together.
- Café consumption requires a warehouse, issues warehouse stock and creates its log in the same transaction. Received quantities in a daily log are informational; receipts still belong to purchase/receipt writers. The form exposes a required warehouse selector.
- Product opening purchases post receipts once. New products initialize empty quantity metadata and ignore client-supplied projection quantities. Product updates exclude `qty`; variant removal rejects nonzero warehouse quantities or serials even when the aggregate is zero.
- Scheduled `purchase:auto` locks eligible ordinary products in a transaction, writes purchase lines and posts a receipt. A subsequent run reevaluates the alert threshold. Tracked products are excluded from anonymous automatic receipts.
- Warehouse creation delegates empty projection initialization to `InventoryMovementService`, preserving existing warehouse product selectors without inventing a stock movement.

All eight Phase 4c controller entries are removed from `LegacyStockShadow::WRITERS`. The four Phase 4b commercial controllers remain. `INVENTORY_LEGACY_LEDGER_MODE=off` controls those commercial shadow records only; it never disables authoritative Phase 4c postings.

`LegacyInventoryPosting` supplies document attribution (`legacy:{table}`), active-company checks on reversal and compatibility with pre-cutover records. It does not mutate projections. `InventoryMovementService` owns stock locks, policies, identities, quantities and valuation. Callers hold the document lock and wrap document, line and stock effects in one transaction.

## Historical documents and activation

Applied reversals preserve the original posted quantities, identities and costs. Documents predating applied history use persisted transaction lines where these are sufficient; compensation movements have a separate source suffix so a later edit cannot reverse them again. Opening and shadow movements are retained as history.

A historical production without a usable ingredient snapshot, a historical tracked line missing its required identity, or a historical combo packing slip without recorded components requires reviewed recovery before deletion. The code rejects these cases instead of guessing from the current recipe. Historical in-transit aggregate discrepancies also require reconciliation before deployment.

This is an implementation cutover, not a production activation or UAT sign-off. Before deployment:

1. Back up retained data and pause all stock writers, including the scheduler.
2. Complete reviewed company/batch ownership backfill and stock identity cleanup.
3. Seed ledger openings with `erp:stock-opening`; run `erp:stock-reconcile` and resolve all unexplained differences. Do not use rebuild to hide historical business discrepancies.
4. Rehearse the migrated controllers on retained-data UAT, including edits/deletes of historical documents. Obtain clean reconciliation and sign-off before activation.
5. Deploy the code and resume writers together. Rolling back to direct writers after applied postings requires a reviewed ledger/data recovery plan; the shadow flag is not a cutover rollback mechanism.

Phase 1 isolation and optional-capability activation gates remain in force. Manufacturing BOM/yield/accounting work, legacy commercial numbering/accounting convergence and retained-data migration remain assigned to their owning phases.

## License and installer decisions

The owner explicitly confirmed on 2026-10-04 that their SalePro license permits this fork/rebranding and removal of purchase-code verification. This records the owner's attestation, not independent legal verification. The unused always-successful `purchaseVerify` method is removed so installation does not claim that a license was externally verified.

The old remote add-on installer is retired. Its four existing HTTP endpoints return 410 without network calls, ZIP extraction, migrations or module activation. Deployment of reviewed packages belongs to the normal release process. No signed package manager or new package source is introduced. The separate legacy `AutoUpdateTrait` is outside this installer change and remains a Phase 11 dependency/security review item.

The destructive-route findings were already resolved in `ff9310e`: `/update-coupon` and its method were removed; `/setting/empty-database` became POST-only, restricted to active Owner/Admin, with typed `DELETE ALL DATA` confirmation. The progress document now agrees with the dedicated audit.

## Validation

`LegacyInventoryCutoverTest` exercises the actual controllers and scheduled command on isolated fixtures. Coverage includes adjustment replacement and rollback, variants, completed/pending/in-transit transfers, serial relocation/reversal, division UOM historical transfer deletion, CSV conversion and rollback, missing-row policy, café log rollback, exchange atomicity, production reversal after recipe changes, duplicate packing rejection, combo reversal, incomplete historical snapshots, company mismatch, product openings, scheduled replay, empty warehouse initialization and retired installer responses.

The targeted SQLite inventory/shadow/command/destructive-route suite passes (75 tests, 1,315 assertions). [MySQL 8.4 CI at `dfd10c4`](https://github.com/vigneshsinna/zolo-erp-pro/actions/runs/37184497149) passes the full foundation suite (251 tests, 2,609 assertions, none skipped) and the seeded original-schema web/API/accounting suite (28 tests, 204 assertions). This includes the cutover controller tests and row-lock concurrency. The seeded MySQL web suite asserts applied movements for transfer, adjustment and café routes while retaining commercial shadow checks and seed-baseline differences.

The rendered café form was checked in the in-app browser using fixture data: modal opens, the warehouse is required, selecting a warehouse satisfies validity, and no console errors appear. This is form verification; it does not claim production capability activation or a full authenticated browser rehearsal.

The Phase 4c quantity search has no active nonzero projection mutations outside `InventoryMovementService`. `ProductTransfer::qty` is a transaction line, and explicit zero values initialize empty metadata. Repository-wide searches still find Phase 4b commercial projection writers, so the full Phase 4 exit criterion is not met.

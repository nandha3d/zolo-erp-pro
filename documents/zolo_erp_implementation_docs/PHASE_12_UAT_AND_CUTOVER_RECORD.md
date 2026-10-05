# Phase 12 customer UAT and cutover record

Status: **template; no customer acceptance or production deployment signed**.

Synthetic rehearsal history, 2026-10-05: the first disposable repository-seed rehearsal for company 1 / branch 1 / FY 1 completed opening stock but produced **21 stock differences**. Those differences were traced to inconsistent repository seed data. The seed was corrected at the source; the later recorded synthetic rehearsal reports **zero stock differences** and passes `erp:health`. This remains engineering fixture evidence only. It does not represent customer retained-data reconciliation or customer acceptance. Customer, accountant, security and recovery signatures remain pending. See [the closure status](PHASE_1_12_CLOSURE_STATUS.md) for the historical source hash and private evidence paths.

The phase worktrees are consolidated into the single `main` checkout. Their recoverable backup is `V:/pers/Freelance/zolo-erp-pro-worktree-backup-20261005-152501`. Final cumulative browser/device verification and the full six-suite MySQL matrix are **running; results pending**. Apache/Nginx private-file deny rules still require validation on the actual deployment server. Neither current software acceptance nor production cutover is approved by this record.

Record customer/company/branch/FY, operator/accountant, release SHA, prior compatible release, source system, opening-only versus detailed-history scope, source freeze time, source hash, mapping reviewer, rehearsal/final target identifiers, backup checksum, off-site retention policy and matching restore proof. Keep customer data and recovery secrets in private deployment records.

## Reconciliation evidence

For each stock identity/warehouse and account/invoice, record source amount, target amount, difference, explanation, owner and resolution. Totals must include stock base quantity/value, AR invoice/advance balances, AP invoice/advance balances, cash/bank and other opening accounts, trial-balance debit/credit, and tax/document snapshots for parallel transactions. Attach the agreed source extract hash and import batch/hash, validation preview and committed reconciliation JSON. No unexplained difference is acceptable.

## Acceptance cases

| Pack | Required operator flow | Source/target comparison and evidence | Reviewer/result |
| --- | --- | --- | --- |
| General Trading | Purchase, sale, payment, return; keyboard and mouse entry | Stock, journal, open items, tax, numbers, print, reversal/retry | Pending |
| FMCG | Batch receipt, FEFO sale, paid/free quantity, expiry/damage | Batch/base quantity, valuation, disposal, UOM | Pending |
| Textile | Job-work send/receive, service bill, wholesale sale/payment | 500 MTR sent, 480 MTR returned, loss, service accounting, long print | Pending |
| Timber | Dimension receipt, selected-piece sale, conversion | Frozen invoice volume, input/output/offcut/waste values | Pending |
| Solar | Project, serial procurement/allocation, dispatch/install, invoice/payment | Serial ownership, stock, margin, warranty/replacement history | Pending |
| Security | Foreign company/branch IDs, disabled capability, role denial, locked/backdated import | Safe refusal; no stock/journal/file effects | Pending |
| Delivery | Provider failure and retry after posting | Posted transaction intact, outbox identity and safe failure evidence | Pending |
| Restore | Database plus uploaded documents on isolated target | Password/checksums, restricted target, journals/open items/stock/files | Pending |
| Rollback | Previous compatible code on additive schema with writers paused | Reads/health/caches, explicit limits for new source types | Pending |

## Performance and operator checks

Record dataset size, warm/cold conditions, hardware, browser/device, samples and p95 for POS readiness (budget 1.5 s), autocomplete (300 ms), 20-line posting excluding messaging (1.0 s), first list page (800 ms) and financial summary (3 s or async/export). Attach exact measurements; historical fixture numbers are not a production SLA. Record keyboard completion, focus/error behavior, desktop/tablet/mobile layout, long print and actual A4/thermal/dot-matrix devices.

## Freeze and recovery decision

Record old-entry pause, final snapshot/delta disposition, clean final target, replay/reconciliation result, source/target approval, writer enable time and monitored outbox/health checks. Opening-only batches cannot append transaction deltas to a posted target. Record rollback trigger, decision owner, compatible code SHA, paused workers/writers and approved recovery strategy for transactions newer than the backup. Never use the isolated rehearsal restore command to overwrite production.

Required signatures: customer operator, accountant, company isolation/security owner, deployment/recovery owner. Until signatures and evidence are present, production gates remain closed. Phase 13 is undefined in the supplied canonical plan and has no acceptance record here.

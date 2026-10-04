# Phase 11: UI, API and security completion

## Observed behavior and implementation scope

Phase 9–10 checkpoint `a0c01bc` already uses shared posting, context authorization, capabilities, immutable records, retries, period locks and industry fields. Company financial-year setup and capability/series API setup exist. Shared commercial entry already supports keyboard and mouse entry, inline masters, drafts, party balances and print actions.

The remaining implementation extends those owners with an authorized company setup workspace and context picker, common capability/permission navigation, bounded API read contracts, safe request correlation and deployment-facing controls. Existing public database export writes unencrypted dumps into `public`; it must be retired before deployment.

This package does not remove the prior full company-isolation or optional-activation acceptance gates. Phase 12 follows passing Phase 11 checks. Canonical document 26 defines phases 0–12; no Phase 13 definition exists in the supplied pack.

## Delivered behavior and proof

`/workspace` shows links only when the company capability, effective role permission and relevant global gate allow them. Context changes validate company, branch and financial year together before updating the session. Company choices expose only active memberships and authorized branches. `/workspace/setup` works before financial-year resolution and lets administrators save business details, branches, warehouses and print preferences without rewriting stock or historical documents. Existing FY, tax, profile, role and account-mapping owners remain responsible for those settings.

Setup and capability changes append company/actor-scoped before/after audit history. Branch and warehouse retries preserve the same record; conflicting retries fail. Optional activation still requires the existing full foundation acceptance.

Product, party and commercial document reads use explicit resources and bounded pagination. Existing company/branch ownership and response envelopes remain compatible. Read endpoints now check effective company permissions and core capabilities, including financial reports. API login has an IP-based limit. Shared mutation retries and posting owners remain unchanged. Existing document delivery outbox handles dispatch; no unrequested external webhook destination was configured.

Every HTTP response carries a validated/generated request ID. Structured request logs contain route, actor/company/branch, status and duration without URL queries or request bodies. Database failures return a safe 503 rather than redirecting into the installer or logging SQL credentials. The instance-wide public database export is retired with 410; Phase 12 supplies operator-only private backups.

SQLite proof: workspace/security **9 tests / 49 assertions**; existing company/API reader regression **12 tests / 147 assertions**. Disposable browser proof verifies navigation, form rendering and a successful business-details save. [Saved setup screenshot](../phase11-12-proof/company-setup.png). Existing shared entry supplies keyboard, inline-master, draft, print and profile fields; broad operator acceptance and retained-data isolation remain deployment gates.

Migration `2026_10_09_000001_create_company_setup_audits.php` is additive and refuses destructive audit rollback. The new `accounting.reports.view` permission must be assigned explicitly to non-administrator reporting roles. Browser backup removal is an intentional security change; operators use the private backup command instead.

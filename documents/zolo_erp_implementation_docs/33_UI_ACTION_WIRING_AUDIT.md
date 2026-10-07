# UI action wiring audit — 7 October 2026

Branch: `ui`. Comparison: `main` plus the current uncommitted UI work. Existing navigation consolidation and other edits were preserved.

## Findings and repairs

| Area | Failure | Repair |
| --- | --- | --- |
| Delivery Challan and GRN | Inline JavaScript ran before jQuery; Add row, list, drawer and edit handlers failed together. | One shared script loads through the existing scripts stack after dependencies. |
| Material rows | Hardcoded product/unit IDs and simulated quick creation could save the wrong item. | Catalog selection and persisted product/party APIs supply actual IDs. Server validates company-owned references, quantities and rates and recalculates totals. |
| Material lists | List reopening, docking and document links were incomplete. | List controls work; document URLs load the entry screen in edit mode. Existing rates, tax, units, remarks and transport fields survive loading. |
| Conversions | Redesigned sales/purchase forms used different field targets; shared posting did not mark DC/GRN converted. | Correct field mapping and shared posting transaction link the source, lock it and reject another conversion. Converted sources cannot be edited or deleted. |
| Review and Save | Review had no backend action; zero-tax preview values failed strict comparison during Save. | Review uses shared server pricing; Save posts the reviewed canonical items. Zero-tax comparison handles consistent numeric types. |
| Drafts | Save as submitted an unsupported posting status; saved drafts could not reliably resume in the normal UI. | Existing draft API supplies versioned save/resume. Source links, edit context, quantities, rates, transport, charges and payment fields restore. Successful posting consumes the owned draft. |
| Reset and edit | Old source IDs and retry keys remained after reset; saved metadata and payment details were lost. | Reset clears transaction context. Edits preserve metadata, batch/serial fields, four-decimal rates and supported payment method/account/amount. Mixed settlements require the existing reviewed settlement workflow. |
| Replacement | An already reversed document could produce another replacement with a new retry key. | Shared reversal service rejects another replacement while retaining valid retries. Historical entries show Reversed and retain View/Print. |
| Item modes | Product/Service/Mixed and Cash/Credit selectors were unwired. Service products were absent from the normal catalogs. | Selectors affect their actual fields and catalog filters. Catalog queries include services/digital items and both null/false non-variant flags. |
| Multi item | Cached array indexes could select a different item after quick creation. | Stable product IDs, refreshed mode filtering and escaped names/codes keep selection accurate. |
| Purchase charges | Bill sundry controls were displayed without a supported payload. | Existing freight and bill-discount fields connect to shared pricing/posting. Drawer, draft, edit and visible totals preserve them. |
| View, Print and export | Sidebar handlers were missing or displayed placeholder alerts; Sales View used the wrong endpoint. | Existing document JSON populates the detail modal. Native print supports document/register printing and Save as PDF. Excel-compatible UTF-8 CSV exports the filtered visible register. |
| Filters and status | Range lacked an end date; purchase status incorrectly displayed Paid. | Both date bounds filter the list. Paid/Partial/Due use amounts and the existing status conventions. |
| Layout | Fixed heights clipped controls and caused footer totals to overlap the list. | Scrollable tables, wrapping footer and responsive workspace/list heights keep controls reachable. |

## Verification

- `CommercialUiWiringTest.php` and `SharedCommercialTest.php`: **24 tests, 145 assertions passed**. Covers row validation/recalculation, invalid update rollback, conversion locking/linkage and retries, unit-code translation, zero-tax save, four-decimal rates, metadata, draft consumption, service catalog, payment details and replacement retry protection.
- `DeliveryChallanWebTest.php` and `GoodsReceivedNoteWebTest.php`: **8 tests, 44 assertions passed**. Fixtures now use real catalog references; rendering checks dependency order.
- `OptechVoucherWebTest.php` and `OptechMasterWebTest.php`: 23 tests passed in the initial combined run. Three posting tests exposed missing accounting fixtures; after supplying test-only posting accounts, all three passed (**11 assertions**). No accounting configuration was seeded into the user's database.
- Final command-center render/JSON subset: **4 tests, 22 assertions passed**.
- Changed PHP files pass `php -l`; both shared UI scripts pass Node syntax checks; `git diff --check` reports no whitespace errors.

Browser verification used the actual application against a disposable SQLite copy on localhost, with normal capability checks and fixture accounting mappings. Verified:

1. Challan/GRN row creation, product selection, persisted quick product/party creation, save, load, transport fields, list controls and conversion.
2. Challan to Sales and GRN to Purchase post successfully and update their source status.
3. Sales draft reset/resume/post removes the draft; purchase service draft restores freight/discount and posts the expected total.
4. Purchase replacement restores Bank settlement, account, amount, freight and discount after reload.
5. View, CSV download, voucher posting and master creation reach their backend endpoints successfully.
6. At 800px width, purchase footer/list do not overlap and the document has no horizontal page overflow. Tables remain horizontally scrollable. Final console check is clear.

Screenshots: `scratch/ui-wiring-challan-proof.jpg`, `scratch/ui-wiring-purchase-responsive-proof.jpg`.

## Remaining configuration and scope

The local application database has no chart-of-accounts/semantic posting mappings. Financial posting correctly refuses to proceed until the company configures those accounts. This is a business configuration prerequisite; the audit does not invent real ledger mappings. Browser posting checks used mappings only in the disposable database.

CSV is the explicit Excel-compatible export; PDF uses the browser's print dialog. No separate XLSX/PDF generation service was added. Unsupported split settlements and unreviewed legacy documents retain the existing backend restrictions.

This audit covers the changed commercial entry screens, material documents, voucher/master actions and related navigation. It does not certify every optional ERP module or every reference screen in the modernization plan. Changes remain local and uncommitted; no deployment was performed.

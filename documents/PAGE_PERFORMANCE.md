# Page load and navigation performance — 2026-10-08

Changes apply to the shared application layout and the local PHP server. Existing company, branch, authorization, draft, and posting behavior is preserved.

## Changes

- Reveal the page after DOM initialization rather than waiting for the window load event and an extra 100 ms. Slow fonts, images, and unrelated assets no longer hold the whole workspace behind the loader.
- Load the installed Excel/PDF dependencies only when their DataTables export button is clicked. Keep the existing export actions, customization, Arabic fonts, and retry handling. CSV and print remain immediate.
- Load Chart.js only on the dashboard and sales chart report. Load TinyMCE and Dropzone only on the product and invoice forms that use them.
- Use a file modification timestamp for `charts-custom.js`, replacing a cache-busting timestamp that changed on every request.
- Serve the existing DataTables FixedHeader 3.1.6 and Responsive 2.2.3 plugins locally instead of requiring the CDN during every navigation. The five vendored files retain their upstream license headers; their original URLs are the corresponding `https://cdn.datatables.net/fixedheader/3.1.6/{js,css}/...` and `https://cdn.datatables.net/responsive/2.2.3/{js,css}/...` URLs previously in the layout. No dependency versions changed.
- Add ETag/304 responses to public scripts, styles, and fonts on the PHP development server. Add revalidation headers for the same public asset directories under Apache. Dynamic pages and business documents do not receive these public cache headers.
- Disable Debugbar in the local `.env` and in the example configuration. Keep application error handling and logging enabled.
- Run the local server with OPcache. Set its timestamp check interval to two seconds: checking every source file on every request (`revalidate_freq=0`) was materially slower on Windows. PHP code edits may take up to two seconds to appear.
- Fix the customer page's missing virtual keyboard dependency, which previously threw before its DataTable initialized. The dependency remains limited to pages that use it.

Sales/purchase pages avoid **2,590,666 bytes (2.59 MB) of uncompressed initial JavaScript**, net of the small export loader. This is a source asset size comparison, not a compressed network transfer measurement.

## Matched runtime measurements

Authenticated GETs against the same local database and optimized views, using `127.0.0.1` for both servers. Before: PHP without OPcache, Debugbar enabled. After: OPcache with a two-second timestamp check, Debugbar disabled. Each page was warmed up; values are medians of three alternating samples. These are complete HTML response times, not browser LCP/INP measurements.

| Page | Before | After | Reduction |
| --- | ---: | ---: | ---: |
| Dashboard | 1,039 ms | 378 ms | 63.6% |
| Sales | 1,270 ms | 510 ms | 59.8% |
| Purchases | 1,221 ms | 431 ms | 64.7% |
| Customers | 918 ms | 315 ms | 65.7% |
| Chart of accounts | 851 ms | 273 ms | 67.9% |

Raw local measurements: `scratch/performance-comparison.jsonl`. Temporary comparison servers on ports 8094 and 8095 were stopped. The measured runtime settings were applied to the existing port 8080 server and saved in `run-erp.bat`. A final authenticated sales request on port 8080 returned HTTP 200 in 431 ms with Debugbar disabled.

## Verification

- `node --test tests/JavaScript/table-exports.test.cjs`: four passing tests for deferred loading, shared concurrent loads, action arguments, Arabic font ordering, failure/retry, and unsupported browsers.
- `php vendor/bin/phpunit tests/Feature/PagePerformanceWebTest.php tests/Feature/DocumentEntryWebTest.php tests/Feature/AccountingWebTest.php`: 16 passing tests, 88 assertions.
- HTTP assertions: static asset returns ETag, a matching conditional request returns 304 with no body, and the login page remains privately cached.
- Real browser: customer rows and export controls initialize; actual Excel and PDF files download. The Excel worksheet contains the customer name and the PDF has a valid signature. Sales and purchase forms render with draft controls and no initial heavy export/editor/chart libraries. Dashboard charts render; the product form initializes its rich-text editor and image-upload tools. No new browser console errors on those pages.

## Limits

This does not certify every optional ERP module, large production datasets, concurrent-user capacity, or real-user Core Web Vitals. Apache configuration was not exercised on this PHP development server. Existing database latency and company/branch query caching fixes documented in session memory were reused without being reimplemented.

## Follow-up — 2026-10-17

The shared shell now renders page content as HTML arrives. It no longer hides the whole page until JavaScript finishes, and it no longer adds the legacy one-second entry animation. The optional Google Fonts stylesheet loads without blocking initial rendering.

Shared CSS and JavaScript URLs include their file modification time and size. The PHP server serves matching versions with `Cache-Control: public, max-age=31536000, immutable`, so repeat navigation can reuse them without a conditional request. Source changes produce a new URL. Unversioned and stale-version URLs still revalidate; matching ETags still return an empty 304 response. Apache has an equivalent rule for versioned URLs. Dynamic pages remain private. Business documents are excluded from public asset versioning.

The sidebar reads its permission list once, rather than querying each menu permission. Workspace navigation evaluates its permission group with one fresh actor/context validation. Read-only capability and GST UI lookups reuse the context already authorized by middleware. Hot schema checks reuse the existing schema cache, which is invalidated by DDL. No business data or write authorization is cached by these changes.

### Measurements

Measurements use company 14, the same local database, an authenticated admin, OPcache and disabled Debugbar. Two temporary loopback servers used the previous and updated application classes/layouts. Each page was warmed first. Five samples alternate before/after, reversing their order each round. Values are medians of complete HTML responses; they exclude browser rendering, asset downloads and follow-up AJAX. Temporary servers were stopped after measurement.

| Page | Before | After | Change |
| --- | ---: | ---: | ---: |
| Dashboard | 946.6 ms | 739.1 ms | 21.9% faster |
| Sales | 966.4 ms | 828.5 ms | 14.3% faster |
| Purchases | 1,005.6 ms | 883.5 ms | 12.1% faster |
| Customers | 660.9 ms | 660.8 ms | Approximately unchanged |
| Workspace | 819.0 ms | 635.0 ms | 22.5% faster |
| Job Work | 699.0 ms | 756.0 ms | 8.2% slower in this sample |

Customers and Job Work had variable timings, so a separate five-sample alternating check was run after the other validation finished. Customers measured 711.8 → 683.8 ms (3.9% faster), and Job Work measured 747.8 → 670.5 ms (10.3% faster). These small samples do not establish a stable latency improvement on those two pages. Their shared browser caching and removal of the hidden-content/animation delay apply regardless of the server timing variation.

Separate kernel query counts were Sales 140 → 107, Purchases 131 → 100, Customers 47 → 37, Workspace 107 → 49, Job Work 86 → 76, and Dashboard 85 → 85. Capability cache warmth can change these counts by a few queries. The operator sidebar regression test confirms zero permission queries while rendering its menu from the provided permission list.

Raw evidence is in `scratch/speed-matched-comparison.jsonl`, `scratch/speed-matched-confirmation.jsonl`, `scratch/speed-query-summary.json`, and the `scratch/speed-baseline-*` / `scratch/speed-after-*` files. The initial sequential measurement on port 8080 was noisy and is retained as `scratch/speed-http-before.jsonl` / `scratch/speed-http-after.jsonl`; it is not used for the response-time claims above.

### Validation and limits

- 52 targeted tests pass: PageSpeedTest, VersionedAssetTest, WorkspaceSecurityTest, GstSetupTest, IndustryProfilesTest and CapabilityEngineTest. The earlier CapabilityEngineTest/BranchIsolationTest run also passed 28 tests.
- Regression coverage checks grouped authorization, role overrides, revoked grants/membership, inactive roles, foreign company denial, sidebar permission reuse, context reuse, and asset URL changes after source changes.
- Live HTTP checks confirm immutable caching for the current version, revalidation for missing/stale versions, empty 304 responses, and private login responses.
- Browser smoke checks cover Dashboard, Sales register/entry, Purchases register/entry, Customers, Workspace and Job Work. Registers display existing data; dashboard charts and navigation initialize. No console errors were observed on those pages. Purchase screenshot: `scratch/page-speed-purchases.png`.
- PHP syntax and scoped whitespace checks pass. No dependency installation, database migration, commit or deployment was performed for this request.
- This is local development evidence, not a production capacity or Core Web Vitals certification. Apache rules were not exercised. Optional modules and large datasets were not exhaustively profiled.

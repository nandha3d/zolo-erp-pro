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

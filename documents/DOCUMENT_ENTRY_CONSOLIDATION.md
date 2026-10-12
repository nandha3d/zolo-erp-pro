# Document entry consolidation

Decision record for removing the standalone "Fast Entry" screens. **F2 and F12 are keyboard accelerators into the normal
`/sales` and `/purchases` pages, not separate modules.** Every other document follows the same rule: a shortcut opens
the existing document page in its new-document state and never owns a second implementation of that document.

## Architecture

```
Sales page /sales ─────────┐
Purchase page /purchases ──┤
Sales / Purchase API ──────┼── Legacy-compatible form payload ── LegacyCommercialCommand (legacy:true adapter)
POS ───────────────────────┘                                          │
                                                       Shared Commercial Application Services
                                                                        │
                                                       stock · accounting · numbering · tax
```

* The normal pages keep posting through their existing form contract. Nothing was rewritten to the JSON payload of the
  retired entry screen.
* `App\Services\Commercial` and the internal `/commercial/{sale|purchase}/…` endpoints (search, party, previous rates,
  clone, drafts, preview, posting, payment, reversal, receipts) remain. Only the entry **UI** routes were removed.
* Removed: `backend/commercial/entry.blade.php`, `public/js/commercial-entry.js`, the `?entry=fast` adapter hijack, the
  `commercial/{sale,purchase}/entry` routes, `CommercialController::entry`, the empty `Modules/OptechSpeedBilling`
  scaffold, and the `sales.fast_counter` / `purchases.fast_entry` capabilities. `public/css/commercial-entry.css` stays
  because the normal pages and the compliance screens still use it.

## Shortcuts (one registry: `App\Services\Platform\DocumentShortcutRegistry` → `public/js/document-shortcuts.js`)

| Key | Opens | Notes |
|---|---|---|
| F2 | `/sales?new=1` | blank bill, focus on the customer |
| Shift+F2 | Sales Returns, new state | the Returns register opens its own "Add return" step |
| Ctrl+F2 | — | **unassigned**: Sales Order has no proven lifecycle (`sale_status = 2` means Pending) |
| Alt+F2 | `/delivery-challans?new=1` | |
| F12 | `/purchases?new=1` | |
| Shift+F12 | Purchase Returns, new state | |
| Ctrl+F12 | — | **unassigned**, see "Purchase Order" below |
| Alt+F12 | `/goods-received-notes?new=1` | |
| Alt+F10 | `/quotations/create` | |
| F6 | new item | form-local only (never global); accounting F4–F9 are untouched |
| ? | shortcut help | never while typing |

Rules: one listener in the authenticated layout; permission-filtered for UX only (the target page authorises on the
server); skipped while a modal/dialog owns the keyboard; POS uses a different layout and is unaffected; F-keys work from
form fields; `preventDefault()` is called for recognised F-keys. The keys are accelerators only: **no sidebar or toolbar
entry repeats them** (user decision, 2026-10-08); the visible path is each page's own New button and the `+` of its draft
tab strip (whose tooltip names the key), and `?` lists every key.

Pressing a key **again on its own page opens one more draft**: whatever is on screen is saved as a draft first (no
dialog), then a blank draft tab starts; an untouched blank tab is reused rather than piling up empty tabs. Only when the
draft cannot be saved (10-draft limit, a blocking reference, drafts unavailable) does a *Discard / Cancel* dialog appear.
A key for another page saves the current draft before navigating.

## Draft tabs on every document page (`public/js/document-drafts.js`)

One engine, one adapter per page. Every edit is autosaved (1.5 s after the last change) into a server draft of the
page's kind; the page posts through its own controller, which drops the draft (`CommercialDraftService::forgetPosted`)
only after a successful post.

| Page | Kind | Adapter | Drafts API |
|---|---|---|---|
| Sales / Purchase bills | `sale` / `purchase` | `document-workspace.js` | `/commercial/{kind}/drafts` |
| Delivery Challan / GRN | `dc` / `grn` | `material-document.js` | `/documents/{kind}/drafts` |
| Sales / Purchase Return (legacy and compliance forms) | `sreturn` / `preturn` | `form-drafts.js` | `/documents/{kind}/drafts` |
| Quotation | `quotation` | `form-drafts.js` + page hooks (lines replayed through the product search) | `/documents/quotation/drafts` |

Only a real user edit makes a form worth saving (typing, picking or clicking inside the form, rows the user adds or
removes, or the app's own copy action); fields a page fills by itself after loading never create a draft. Closing a tab
is final: an autosave still on its way is awaited and whatever it created is deleted; a blank untouched tab closes
without asking.

A return draft belongs to the bill it returns: from the return register its tab opens that bill's return page
(`create?reference_no=…&draft=ID`). Permissions per kind: `CommercialDraftService::KINDS`.

## Normal-page workspace (`public/js/document-workspace.js`, `document-aids.js`)

* **Draft tabs**: each tab owns a complete snapshot of the form (header fields, charges drawer, every line with its
  tracking, payment mode and amount, links, editing state, references). Switching serialises the current form, then
  replaces the whole form from the target snapshot; snapshots are never merged.
* **Server drafts** (`CommercialDraftService`, table `sale_drafts` — a historical name, it stores both kinds): scoped by
  company, branch, financial year, user and kind; optimistic `version` (409 on a stale save); 10 open drafts per
  user + company + kind (branch not part of the quota; never auto-evicted); 2 MB payload cap (rejected whole, never
  truncated); pruned 30 days after the **last edit** (lazy on list + `commercial:prune-drafts` daily). Navigation
  metadata (`title`, `party_id`, `party_name_snapshot`) is stored beside the payload and never drives selection.
* A draft never reserves stock, posts accounting, opens items, takes payment, or consumes a document number. The number
  shown on a bill is a preview; `DocumentNumberService` allocates it inside the posting transaction.
* `?new=1` opens a client-only blank tab (no server row until the first meaningful edit) and then removes only `new=1`
  from the URL. A plain `/sales` or `/purchases` shows the register plus the restored tab strip and never auto-opens a bill.
* Autosave 1.5 s after the last meaningful change, on tab switch, on a new-document key and on *Save draft*; unload uses
  `fetch(keepalive)` and is best-effort only (the UI never claims it saved), and is skipped once a post has left the
  page. A 409 offers *Load latest* or *Keep mine as a new tab*. The page's own New / Discard buttons go through the tabs,
  so clearing the form never overwrites the active draft.
* **References** `project_id`, `exchange_return_id`, `purchase_order_id` are references, not trusted state. They are
  validated against the trusted company/branch context when `?new=1` is processed, when a draft is saved, when it is
  loaded and again when posting (`DocumentContextResolver`). An optional stale reference is removed with a notice; a
  required one blocks the draft (and posting with it) until corrected.
* **Entry aids** (config `entry_aids`, replaces the old `fast_entry` list): previous rates, party outstanding and
  pending bills, copy bill, inline party/item, extended stock tracking (variant, existing batch, HSN, weight, freight,
  batch MRP/mfg date, dimensioned pieces, quantity schemes where the capability applies). These are UI assistance
  appropriate to the profile, never a gate on the page.

## Purchase Order (Ctrl+F12) — verified, not enabled

Proven (see `PurchaseOrderLifecycleTest`): the normal form can save `status = 4`; no stock movement, no journal entry,
no open item, no payment (a paid order is rejected); a receipt links through `purchase_order_id` only to an active
order of the same supplier and warehouse; a reversed or foreign order cannot be linked.

Open gaps that keep `documents.purchase_order_shortcut` **off**:

1. Orders draw their number from the purchase **bill** series (there is no `purchase_order` numbering type), so orders
   leave gaps in the bill series.
2. An order has no fulfilled/closed state: it stays status 4 after receipts and can be linked by any number of receipts.
3. The orders list is a status filter on the purchase register; there is no dedicated order edit/convert lifecycle.

Close those, then set `DOCUMENTS_PURCHASE_ORDER_SHORTCUT=true` (or the config default) and add the browser proof.
Sales Order stays unassigned until conversion, fulfilment, numbering, stock and payment behaviour are proven.

## Verification

* PHP: `DocumentEntryTest`, `WorkspaceDraftTest`, `PurchaseOrderLifecycleTest` (fixture company),
  `DocumentEntryWebTest` (real pages against the seeded database), `CapabilityRetirementTest`.
* Browser: `tests/Browser/*.spec.mjs` drive the real `/sales` and `/purchases` pages (see its README).

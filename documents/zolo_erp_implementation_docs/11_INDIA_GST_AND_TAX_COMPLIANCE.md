# 11 - India GST and Tax Compliance

> **Repository:** `vigneshsinna/zolo-erp-pro`  
> **Target:** zoloERP Pro / Laravel 10  
> **Status:** Implementation specification  
> **Basis:** reviewed `main` branch + `optech_erp_modernization_project_plan.html`  
> **Date:** 2026-10-03

> **Product rule:** zoloERP is a general ERP. Textile, FMCG, timber, solar and other verticals must be configurable capabilities or industry packs around one shared sales, purchase, inventory, accounting, tax, document, security and API foundation.


## Purpose

Build GST as a shared compliance layer. Preserve the original plan's GSTIN lookup and GSTR reporting intent while isolating provider/version changes from commercial code.

## Target rules

- UI never decides CGST/SGST/IGST by itself.
- Posted documents keep a tax snapshot so future rate changes do not rewrite history.
- GSTIN format validation is not equivalent to verified active registration.
- Government/provider schemas are versioned adapters and must be reverified before production filing.

## Data model / contracts

Core concepts:

```text
tax_registrations
  company_id, branch_id, gstin, legal_name, trade_name
  state_code, registration_type, status, effective dates, verified_at

tax_categories
tax_rates (effective-dated)
hsn_sac_codes
gst_transaction_projections / export version metadata
```

Store transaction line tax snapshot:

```text
taxable_value
rate
cgst
sgst
igst
cess if supported
hsn_sac
place_of_supply
reverse_charge
```

## Services and ownership

Interfaces:

```text
GstinLookupProvider
TaxDeterminationService
GstProjectionService
GstExportAdapter(version)
```

Tax determination receives seller registration, buyer registration/address, ship-to/place-of-supply, item HSN/SAC, document date and special supply flags.

## Implementation sequence

1. Create registrations/rates/categories reference model.
2. Implement GSTIN provider interface with timeout/cache/audit/manual fallback.
3. Move tax determination out of screens/controllers.
4. Persist tax snapshots on posted documents.
5. Build GSTR projections from posted documents and credit/debit notes.
6. Add versioned export adapter; verify current statutory format at implementation time.

## Acceptance and verification

- TN→TN registered supply produces intra-state split.
- TN→Karnataka produces IGST.
- B2C/unregistered, exempt/non-GST and service SAC scenarios work.
- Credit note reverses correct tax projection.
- Provider outage does not corrupt party or invoice data.

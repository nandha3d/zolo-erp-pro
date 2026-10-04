# Company table ownership matrix

Source inventory for audit F-04, reviewed 2026-10-04 by the Codebase Onboarding Engineer. This is a migration and isolation checklist, not an activation certificate.

The source defines **136 application tables**: the original 123 active literal creators, six additive capability/numbering creators, five stock-ledger creators and two configured Spatie creators. Framework `migrations` metadata is additional. A live database may differ; reconcile its schema before cutover. The company package adds nullable indexed ownership to 32 existing tables and creates four platform tables. It does not cover all business data.

## Interpretation and common acceptance gates

- **E:** Existing core package adds nullable `company_id`.
- **P:** Ownership can be derived from an identified parent, subject to all-reference validation.
- **N:** No current company key or reliable single parent.
- **T:** Shared identity/platform metadata within the application or tenant database.
- **M:** Mixed platform/reference and business settings; split ownership explicitly.

Company/branch requirements below are target contracts. Do not infer ownership from shared actor/user IDs, serialized lists or one of several conflicting references. For every company table, validate legacy references before DEFAULT backfill; require trusted-context writes, scoped Eloquent/raw reads, company-aware validation, appropriate uniqueness and FKs, and HTTP/import/export tests. Child rows must agree with all ownership-bearing parents.

Branch ownership follows verified warehouse/location references where available. Transfers have two branch endpoints. A missing branch source requires an explicit policy; creating a column does not establish historical branch truth.

Controller names refer to `app/Http/Controllers`; API names to its `Api/V1` directory; accounting names to its `Accounting` directory; manufacturing names to `Modules/Manufacturing/Http/Controllers`.

## Platform and identity

| Tables | State / required scope | Readers and writers | Backfill, uniqueness, FK and test requirements |
|---|---|---|---|
| `companies` | Legal-entity registry | Company, backfill, resolver | Keep globally unique company code; authorize status/membership selection |
| `company_branches` | Explicit company, non-null FK | CompanyBranch, backfill, resolver | Existing company/code and id/company uniqueness; test cross-company branch assignment |
| `company_user`, `company_user_branches` | Explicit membership/grants | Company, resolver, backfill | Existing composite membership and branch/company FKs; users use unsigned INT; role override needs effective authorization |
| `users` | T; shared identity with multiple memberships | UserController, auth/registration, commands | Keep identity uniqueness; reconcile legacy warehouse/biller assignments; never infer one legal company from identity |
| `roles`, `permissions`, `role_has_permissions` | T; current database-wide definitions/grants | RoleController, Common, PermissionMiddleware, Spatie | Permission definitions remain shared; effective company role/grants and cache invalidation require integration tests |
| `model_has_roles`, `model_has_permissions` | T; polymorphic identity grants | Spatie, User | Preserve polymorphic keys; no current company-membership dimension |
| `personal_access_tokens`, `mobile_tokens`, `password_resets` | T; identity/device metadata | Sanctum, AuthController, ValidateMobileToken, password reset | Tokens authenticate identity; they do not grant/select company access; keep global token uniqueness |
| `failed_jobs` | T; queue failure storage | Framework | Company belongs in authorized job context/payload, not an invented row owner |
| `languages`, `translations` | T; shared application localization | Language/translation controllers, Common, providers | Preserve global definitions; avoid inventing company language ownership |
| `currencies` | M; shared reference plus mutable exchange-rate policy | CurrencyController, Common, settings, transactions | IDs are unsigned BIGINT; separate shared identity from company rate policy; reference FKs require compatible widths |

Configured Spatie table names must be checked against deployed `config('permission.table_names')`.

## Additive capability and numbering ownership

| Tables | State / required scope | Readers and writers | Constraints and remaining gates |
|---|---|---|---|
| `capabilities`, `business_profiles`, `business_profile_capabilities` | T; shared registry and presets | CapabilitySeeder, CapabilityService | Stable unique keys and restricting parent references; optional activation remains gated |
| `company_capabilities` | Explicit company override | Company administrator API, CapabilityService | Unique company/capability pair, restricting FKs, committed cache invalidation; configuration does not authorize optional operations while gated |
| `document_series` | Explicit company/branch/FY/type/code | DocumentNumberService, administrator API | Unique code/default per scope; actor and same-company branch/FY revalidated before allocation; used series immutable |
| `document_number_reservations` | Company and owned series/source | Shared commercial/payment/accounting writers | Unique sequence/formatted number/source binding; allocation and assignment in the source transaction; retained references preserved |

Both additive migrations resume missing indexes/FKs after committed MySQL CREATE TABLE interruptions. These tables do not establish ownership for remaining legacy modules.

## Stock ledger ownership (Phase 4)

| Tables | State / required scope | Readers and writers | Constraints and remaining gates |
|---|---|---|---|
| `stock_movements`, `stock_movement_lines` | Company, nullable until backfill; stamped by the movement service from the authorized context, or from the owned warehouses when no context is passed | `InventoryMovementService` only (including `LegacyStockShadow` and `erp:stock-opening`); availability/reconciliation read | Immutable by model guard (corrections are reversals); unique idempotency key and reversal link; restricting FKs to products, warehouses, identities, batches and companies; mandatory ownership and company-aware uniqueness remain gates |
| `stock_identities`, `stock_dimensions` | Company via product; one serial or piece per product/type/number | `InventoryMovementService` | Single location and status per identity; restricting FKs; dimension row unique per identity |
| `product_uom_conversions` | Company via product | `UomConversionService` reads; no maintenance screen yet | Unique per product/from/to unit; restricting FKs |
| `product_batches` (extended) | Gains nullable indexed `company_id`, `mfg_date`, `mrp`, `status`; ownership still derives from the product | Movement service and legacy controllers | `company_id` is not yet populated by `erp:backfill-company-context` for existing batches |

Migration `2026_10_04_000001_create_stock_ledger_tables` is resumable after committed MySQL DDL: it creates only missing tables, adds only missing columns, indexes and foreign keys, and refuses a table lacking owned columns or an index of the wrong shape. Proof: `StockLedgerMigrationTest` interrupts it at eight statement boundaries on disposable MySQL and reruns it with retained rows.

## Existing 32-table ownership package

All rows here are E-state nullable indexed company keys. Only warehouses additionally receives a branch key. Required non-null/company FKs and company-aware uniqueness remain cutover work.

| Tables | Company/branch boundary | Readers and writers | Backfill and constraint requirements |
|---|---|---|---|
| `categories`, `brands`, `units` | Company masters | Master/product/transaction controllers, DataRetrievalService, recipes | Validate category hierarchy and base-unit ownership; preserve product unit zero sentinel |
| `customer_groups`, `customers`, `suppliers`, `billers` | Company masters | Master controllers, commercial services, reports/payment paths | Group/party/source agreement; shared user reference does not make a party global |
| `warehouses` | Company + branch | WarehouseController, stock/commercial/report/operational paths | DEFAULT/MAIN backfill; validate branch/company composite ownership and branch grants |
| `products` | Company master | ProductController/API, ERP services, operations/recipes | Validate categories, brands, units, taxes, batch/variant/component ownership; recipe lists require item-level checks |
| `sales`, `product_sales` | Company header/lines; warehouse branch | SaleController/API, SaleService, reports/packing/returns | Line/sale/product agreement; scoped direct IDs and validators; preserve historical opening semantics |
| `purchases`, `product_purchases` | Company header/lines; warehouse branch | PurchaseController/API, PurchaseService, AutoPurchase, reports | Supplier/product/warehouse/receipt agreement and receipt/accounting parity |
| `payments` | Company finance; document/account/register links | PaymentService, ERP services, partner/payroll/return controllers | All linked owners agree; preserve audited opening-stock account zero sentinel |
| `product_warehouse` | Company projection; product/warehouse agreement | All stock writers, Manufacturing, reports/close | Product ID originally string, warehouse reference signed INT; reconcile types/data and projection uniqueness before FKs |
| `transfers`, `product_transfer` | Company movement; source/destination branches | TransferController, InventoryService, reports | Both warehouses same company; authorize both endpoints; line/product/header agreement |
| `returns`, `product_returns` | Company sales return; warehouse branch | ReturnController and commercial/report/account views | Validate optional original sale, customer, warehouse and product; absent source is not a cross-company exception |
| `return_purchases`, `purchase_product_return` | Company purchase return; warehouse branch | ReturnPurchaseController and supplier/report/account views | Line parent column is return_id; parent table is return_purchases; validate purchase/supplier/product ownership |
| `adjustments`, `product_adjustments`, `stock_counts` | Company inventory; warehouse branch | AdjustmentController, StockCountController | Validate selections and stock-count conversion; preserve quantities/history |
| `expenses` | Company finance; warehouse branch | ExpenseController, payroll, account/report/register paths | Category/account/warehouse/register agreement and posting-date tests; expense_categories is outside this package |
| `accounts` | Company legacy cash/bank account | AccountsController, payments, payroll, reports, Manufacturing | Distinct from chart_of_accounts; do not replace legacy account IDs with chart IDs |
| `chart_of_accounts` | Company chart | Chart/journal/report/mapping controllers, AccountingService/API | Global code unique must become company-aware; same-company parent; currency_id unsigned INT differs from currency BIGINT |
| `fiscal_years` | Company periods | FiscalYear, resolver, backfill, chart seeder | Preserve dates/is_closed; no parallel financial_years; overlap/lock tests on actual posting |
| `journal_entries`, `journal_items` | Company headers/lines | AccountingService, journal/report controllers, commercial APIs | Global entry_number unique; account/header/source agreement; source creator has entry_date but no persisted FY FK |
| `semantic_account_mappings` | Company semantic roles | SemanticMappingController, ErpAddonsSeeder | Global semantic_role unique must become company-aware; account_id references chart, not legacy accounts |
| `inventory_closes` | Company close; optional warehouse branch | PeriodicInventoryCloseController | Global reference unique; null warehouse means all authorized company warehouses; journal/warehouse agreement |

The backfill's frozen reference map checks a subset of relationships. Passing it does not prove complete reference ownership or reader/writer isolation.

## Other commercial, stock and financial tables

| Tables | State / target scope and branch source | Readers and writers | Backfill, unique/FK changes and required tests |
|---|---|---|---|
| `taxes`, `expense_categories`, `income_categories` | N; company business masters | Master, commercial, income/expense, recipe controllers, AutoPurchase | Explicit DEFAULT assignment after audit; scoped master uniqueness/reference tests; preserve historic rates |
| `incomes` | P; company via warehouse/account/category | IncomeController, reports/home/accounts | Validate all owners together; branch via warehouse |
| `money_transfers` | P; company via both accounts | MoneyTransferController, AccountsController | Both accounts same company; no branch can be inferred automatically |
| `quotations`, `product_quotation` | P; company via partner/warehouse/header | QuotationController, home/reports | Customer/supplier/biller and line products agree; test detail and conversion |
| `deliveries` | P; company via sale and packing lists | DeliveryController, packing/purchase/sale | Validate all packing-slip IDs; protect attachments separately |
| `packing_slips`, `packing_slip_products` | P; company via sale/delivery/header | PackingSlipController, challan/delivery/sale | BIGINT headers versus signed INT line references; product/variant agreement |
| `challans` | N/P; company via serialized packing slips | ChallanController, reports | Validate every list member; actor does not establish owner |
| `couriers` | N; company-private configuration or explicitly shared directory | CourierController, challan/delivery/sale | Decide reference/privacy contract before migration; scope private records |
| `cash_registers` | P; company/branch via warehouse | CashRegisterController, sale/return/customer/income | Shared user cannot identify register owner; test branch and cash/report access |
| `damage_stocks`, `exchanges` | P; company via warehouse/products/customer/sale | DamageStockController, ExchangeController | All source/replacement owners agree; global reference_no unique needs company policy |
| `product_batches`, `product_variants` | P; company via product, plus variant agreement | Product/stock/commercial/packing/report/Manufacturing paths | Validate all references, batch type widths and item codes; scoped identities |
| `variants` | N; master scope requires explicit shared/company policy | Product, quotation, return, packing/report | Do not assign company blindly; preserve existing variant IDs |
| `discounts`, `discount_plans` | N; company business rules | Discount/plan/customer/commercial controllers | Serialized product lists need ownership checks; company-scoped applicability |
| `discount_plan_customers`, `discount_plan_discounts` | P; company via both parents | Customer/discount/plan/commercial controllers | Both masters agree; test cross-company attachment |
| `coupons` | N; company commercial policy | CouponController, sale/purchase | Company redemption and cache contract; no reliable parent for historical inference |
| `gift_cards` | N/P; nullable party/user references | GiftCardController, customer/installment/sale | Explicit owner evidence; shared/nullable actor cannot determine company; scoped number lookup |
| `gift_card_recharges`, `deposits` | P; company via gift card/customer | GiftCardController, CustomerController | Orphan/conflict checks; preserve balances; source authorization |
| `reward_points` | P; customer and optional sale | CustomerController, SaleController | Parent agreement; BIGINT customer/sale/user fields versus legacy UINT parents |
| `installment_plans`, `installments` | P; typed source then plan | InstallmentPlanController, sale/purchase | Keep typed-source uniqueness; authorize polymorphic owner; existing plan FK is not sufficient isolation |
| `payment_with_cheque`, `payment_with_credit_card`, `payment_with_gift_card`, `payment_with_paypal` | P; company via payment | PaymentService and payment/transaction controllers | Protect subtype direct IDs and provider data via authorized parent |

## HR

| Tables | State / required company boundary | Readers and writers | Backfill and verification |
|---|---|---|---|
| `employees` | N/P; employer distinct from shared user | Employee/SaleAgent, HRM/attendance/payroll/WhatsApp | Explicit employment owner; reconcile department/warehouse/user membership |
| `departments`, `designations`, `shifts`, `leave_types` | N; company organization/policy | HR master and employee/attendance/payroll controllers | DEFAULT only after policy audit; designations.name global unique needs company policy |
| `attendances`, `overtimes`, `leaves` | P; employee and policy agreement | Attendance/overtime/leave/HRM/payroll | Preserve employee/date/check-in uniqueness; company/employee authorization |
| `holidays` | N; company policy, shared user not owner | HolidayController, HRM/payroll | Explicit historical assignment; test regional/recurring policy isolation |
| `payrolls` | P; company via employee/account/warehouse | PayrollController, home/reports/accounts | All owners agree, posting locks and finance reconciliation |

## Settings, communications and logs

| Tables | State / required boundary | Readers and writers | Migration and access proof |
|---|---|---|---|
| `general_settings` | M; application/licensing plus company business settings | SettingController, Common, providers/auth, nearly all legacy workflows | Split field ownership; pre-auth/global readers cannot use blanket company scope; migrate legal/tax/company policy to explicit company settings |
| `pos_setting` | M/P; singleton defaults and credentials | Settings, commercial/payment/production, AutoPurchase, retrieval service | Unique integer id is not ordinary incrementing singleton per company; define company/branch config contract |
| `invoice_settings`, `invoice_schemas` | N; company numbering/presentation | InvoiceSettingController, SaleController | Replace global counters through atomic company series; preserve layouts/history |
| `hrm_settings`, `reward_point_settings` | N; company policy singleton | Settings, HRM/customer/commercial/home/retrieval | Company singleton reads and policy tests |
| `mail_settings`, `whatsapp_settings`, `sms_templates`, `external_services` | M; credentials/templates/integrations | Settings, mail/payment/WhatsApp/SMS, tenant traits | Explicit global versus company credentials; prevent cross-company private provider use |
| `custom_fields` | N; company business metadata | CustomFieldController and master/transaction/recipe/report paths | Freeze unsafe runtime DDL; inventory existing physical values; migrate normalized definitions/values |
| `barcodes` | N; label-template policy | BarcodeController, LabelsController, ProductController | Decide shared/company templates; scoped modification/printing |
| `printers` | P; company/branch via warehouse | PrinterController, PrinterService, SaleController | Validate warehouse/device owner; protect addresses/credentials |
| `notifications` | M; shared polymorphic recipient and business JSON | NotificationController, User, SendNotification | Carry company/source ownership; recipient identity alone is insufficient; UUID stays global |
| `activity_logs`, `dso_alerts` | N; actor/reference or serialized product data | Controller log helper/settings; DsoAlert/Common/ReportController | Explicit company audit context and computation/read/cache boundary |

## Manufacturing and operational prototypes

| Tables | State / required company and branch boundary | Readers and writers | Backfill, constraints and tests |
|---|---|---|---|
| `productions`, `product_productions` | P; warehouse/product/header | Manufacturing ProductionController | Header BIGINT versus signed INT production_id; validate outputs/ingredients; stock reversal history required |
| `water_tankers`, `water_can_routes` | N; company-private fleet/routes or explicitly shared fleet | WaterLogistics web/API, home | Decide fleet ownership; global vehicle uniqueness policy; branch has no reliable creator source |
| `water_tanker_trips`, `water_can_deliveries` | P; fleet/route/customer/invoice | WaterLogistics web/API, home | All references agree; trip/delivery global numbering review |
| `water_can_inventories` | P; customer balances | WaterLogisticsController | Preserve one balance per customer; globally allocated customer ID already disambiguates owner |
| `cafe_raw_materials` | P; company via product | CafeOperationsController | Actor is not owner; branch absent in creator |
| `cafe_cash_drawers` | N; company/branch cash operations | Cafe web/API, home | Shared cashier identity cannot identify company/branch; explicit drawer contract |
| `tables` | N; company/branch floor/table | Table/Booking/CatalogueQr/Sale/Purchase controllers | Missing floors creator; floor_id tinyInteger default 1; no guessed FK |
| `catalogue_qrs` | P/N; nullable warehouse/table, public slug | CatalogueQrController, CafeApiController | Deliberate public company resolver; preserve global slug disambiguation |
| `repair_device_types` | N; shared/company taxonomy decision | RepairController | Explicit master ownership policy |
| `repair_services` | P; customer/device type/technician | Repair web/API, home | Parent agreement and technician membership; global job-sheet numbering; no creator branch source |
| `project_categories` | N; company business taxonomy | ProjectManagementController | Audited DEFAULT assignment and scoped selection |
| `projects`, `project_tasks` | P; client/category/project | ProjectManagementController | All owners agree; assigned user membership; distinct from absent Project module |
| `bookings` | P; warehouse/customer/table | BookingController | Company/branch agreement; global booking number policy |

## Missing or external schemas

| Reference | Evidence and disposition |
|---|---|
| `floors` | Active TableController raw path, no creator; type/ownership unresolved |
| `kitchens`, `menu_type` | Active ProductController and Manufacturing RecipeController raw paths, no creators |
| `services` | Active SaleController raw path, no creator |
| `product_supplier` | Explicit Product_Supplier model and unused import; no proven active writer or creator |
| `countries` | Test-purpose Country model; no creator |
| `employee_transactions` | Model exists; migration body commented; no active writer found |
| `SaleWarrantyGuarantee` | Referenced sale relationship; model/creator missing; do not invent table name |
| Landlord Tenant/Package/TenantPayment/MailSetting | TenantInfo imports lack local definitions; connection/schema ownership unverified |
| `Modules\\Project\\Entities\\Company` | Absent module class referenced by employee/sale-agent paths; not foundation Company |
| Absent modules | Status file names missing Ecommerce/Woocommerce/Middleware/Restaurant/Project implementations; enabled flag is not schema evidence |

Seven Optech scaffolds add no active table creators. Manufacturing adds the two tables counted above.

## Activation blockers and cutover proof

The source inventory closes discovery only. F-04's operational migrations and full activation remain open.

1. Reconcile all tables against live MySQL, including imported/optional schemas. Resolve M/N classifications before adding ownership keys.
2. Backfill every company-owned record and reference consistently; preserve fiscal dates, 1970 openings, posted values/numbers and legitimate zero sentinels.
3. Convert business uniqueness (chart code, journal number, semantic role and operational numbers) with scoped validators. Preserve globally disambiguating company codes, tokens, UUIDs and public slugs.
4. Validate MySQL type/signedness before FKs. Confirmed mismatches include currency/chart/company fields, production and packing-slip children, reward-point references and string stock product IDs.
5. Enforce same-company parent/child relationships, not only numeric FKs. Add branch grants and multi-endpoint transfer tests.
6. Run actual HTTP list/detail/post/import/report/export/download IDOR tests across web/API/operational routes. Eloquent scopes alone cannot cover raw SQL.
7. Establish authorized FY setup and lock enforcement on actual writers before attaching context broadly. Authentication/context must precede company-dependent Common queries.
8. Restore context for scheduled AutoPurchase/DsoAlert/DailyQuote work. No app/Jobs directory exists; SendNotification is Queueable but not ShouldQueue.
9. Namespace company business caches and user/effective-role authorization caches; reads and CacheForget must share invalidation contracts.
10. Inventory public/documents, public/product/files and public/downloads. Scoped controller queries do not protect direct web-server access to public files; retain references through an audited storage/access migration.

The unsafe scheduled reset was removed in audit correction commit `a435aac`; manual reset is restricted to explicit demo execution. Other scheduled writers still require company integration.

Required proof per table/group: schema/backfill recovery, orphan/conflict checks, trusted-context creation, same-company references, company-aware business uniqueness, branch/date policy, HTTP IDOR, raw-query parity, and job/cache/file coverage. Record N/A only with a source-grounded reason.

## Evidence

- Core and module migrations, especially company context, accounting/mapping, operational prototype and Manufacturing creators.
- BackfillCompanyContext, CompanyContextResolver, ResolveCompanyContext, Common and PermissionMiddleware.
- routes/web.php, routes/api.php, HTTP/console kernels and application providers.
- ERP SaleService/PurchaseService/InventoryService, PaymentService and AccountingService.
- DataRetrievalService, CacheForget, TenantInfo, notifications and scheduled command implementations.
- CompanyFoundationTest, CompanyContextTest and the current MySQL rehearsal tests.

Use [implementation progress](IMPLEMENTATION_PROGRESS.md), [the backfill runbook](COMPANY_BACKFILL_RUNBOOK.md) and [the audit resolution log](../auditing/COMPANY_FOUNDATION_AUDIT_RESOLUTION.md) for current delivered corrections and remaining gates.


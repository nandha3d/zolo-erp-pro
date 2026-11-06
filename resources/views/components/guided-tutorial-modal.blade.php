<div id="zolo-guided-tour-modal" tabindex="-1" role="dialog" aria-labelledby="guidedTourModalLabel" aria-hidden="true" class="modal fade text-left" data-backdrop="static">
    <div role="document" class="modal-dialog modal-lg zolo-tour-dialog">
        <div class="modal-content zolo-tour-card">
            <!-- Header -->
            <div class="zolo-tour-header">
                <div class="zolo-tour-header-info">
                    <div class="zolo-tour-logo-badge">
                        <i class="dripicons-lightbulb"></i>
                    </div>
                    <div>
                        <h5 class="zolo-tour-title" id="guidedTourModalLabel">Interactive Setup Guide & Tour</h5>
                        <p class="zolo-tour-subtitle mb-0">Master the 6 core pillars to launch and operate zoloERP Pro successfully</p>
                    </div>
                </div>
                <div class="zolo-tour-header-actions">
                    <span class="zolo-tour-step-counter" id="tour-step-counter">Step 1 of 6</span>
                    <button type="button" class="zolo-tour-close-btn" data-dismiss="modal" aria-label="Close">
                        <i class="dripicons-cross"></i>
                    </button>
                </div>
            </div>

            <!-- Progress Rail -->
            <div class="zolo-tour-rail">
                <div class="zolo-tour-progress-bar-wrap">
                    <div class="zolo-tour-progress-bar-fill" id="tour-progress-fill" style="width: 16.66%;"></div>
                </div>
                <div class="zolo-tour-rail-steps">
                    <button type="button" class="tour-rail-step active" data-step="1" title="Company Profile">
                        <span class="step-num">1</span>
                        <span class="step-text">Company</span>
                    </button>
                    <button type="button" class="tour-rail-step" data-step="2" title="Warehouses">
                        <span class="step-num">2</span>
                        <span class="step-text">Godowns</span>
                    </button>
                    <button type="button" class="tour-rail-step" data-step="3" title="Products">
                        <span class="step-num">3</span>
                        <span class="step-text">Catalog</span>
                    </button>
                    <button type="button" class="tour-rail-step" data-step="4" title="Parties">
                        <span class="step-num">4</span>
                        <span class="step-text">Parties</span>
                    </button>
                    <button type="button" class="tour-rail-step" data-step="5" title="Invoicing">
                        <span class="step-num">5</span>
                        <span class="step-text">Invoicing</span>
                    </button>
                    <button type="button" class="tour-rail-step" data-step="6" title="Due Clearance">
                        <span class="step-num">6</span>
                        <span class="step-text">Dues</span>
                    </button>
                </div>
            </div>

            <!-- Body / Step Cards Container -->
            <div class="modal-body zolo-tour-body">
                <!-- STEP 1: Company Profile -->
                <div class="zolo-tour-slide active" data-slide="1">
                    <div class="tour-slide-hero theme-purple">
                        <div class="tour-hero-icon"><i class="fa fa-building-o"></i></div>
                        <div class="tour-hero-content">
                            <span class="tour-step-pill">Foundation Setup</span>
                            <h3 class="tour-hero-title">1. Company Profile, GST & Fiscal Rules</h3>
                            <p class="tour-hero-desc">Configure your legal business identity, GST registration classification, official trade names, fiscal calendar years, and billing document series.</p>
                        </div>
                    </div>
                    <div class="tour-slide-details">
                        <div class="row">
                            <div class="col-md-7">
                                <h6 class="font-weight-bold text-dark mb-2">Key Setup Milestones:</h6>
                                <ul class="tour-checklist">
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Legal & Trade Names:</strong> Match official registered records for GST invoices.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>GST Registration Type:</strong> Mandatory selection of Regular, Composition, or Unregistered.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Active Financial Year:</strong> Strict transaction boundaries prevent backdated posting errors.</li>
                                </ul>
                            </div>
                            <div class="col-md-5">
                                <div class="tour-action-card">
                                    <div class="tour-action-label">Quick Destination</div>
                                    <p class="small text-muted mb-2">Open general settings to verify company registration and tax details.</p>
                                    @can('general_setting')
                                    <a href="{{ route('setting.general') }}" class="btn btn-sm btn-block btn-primary">
                                        <i class="fa fa-cog mr-1"></i> Open Company Settings
                                    </a>
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Warehouses & Godowns -->
                <div class="zolo-tour-slide" data-slide="2">
                    <div class="tour-slide-hero theme-sky">
                        <div class="tour-hero-icon"><i class="fa fa-archive"></i></div>
                        <div class="tour-hero-content">
                            <span class="tour-step-pill">Inventory Architecture</span>
                            <h3 class="tour-hero-title">2. Storage Godowns & Branch Warehouses</h3>
                            <p class="tour-hero-desc">Organize your physical inventory across central godowns, branch showrooms, and shop-floor storage locations with real-time stock isolation.</p>
                        </div>
                    </div>
                    <div class="tour-slide-details">
                        <div class="row">
                            <div class="col-md-7">
                                <h6 class="font-weight-bold text-dark mb-2">Key Setup Milestones:</h6>
                                <ul class="tour-checklist">
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Central Storage Hub:</strong> Establish your primary receiving warehouse.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Stock Transfers & DC:</strong> Move items between godowns using delivery challans.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Physical Audit:</strong> Reconcile actual shelf stock with system records effortlessly.</li>
                                </ul>
                            </div>
                            <div class="col-md-5">
                                <div class="tour-action-card">
                                    <div class="tour-action-label">Quick Destination</div>
                                    <p class="small text-muted mb-2">Create and configure your central warehouse and branch locations.</p>
                                    <a href="{{ route('warehouse.index') }}" class="btn btn-sm btn-block btn-info">
                                        <i class="fa fa-building mr-1"></i> Manage Warehouses
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Products & Catalog -->
                <div class="zolo-tour-slide" data-slide="3">
                    <div class="tour-slide-hero theme-emerald">
                        <div class="tour-hero-icon"><i class="fa fa-cubes"></i></div>
                        <div class="tour-hero-content">
                            <span class="tour-step-pill">Catalog & Pricing</span>
                            <h3 class="tour-hero-title">3. Master Products, Categories & GST Rates</h3>
                            <p class="tour-hero-desc">Build your product inventory with standard items, combo/recipe compositions, units of measurement, HSN codes, and automatic profit margin calculations.</p>
                        </div>
                    </div>
                    <div class="tour-slide-details">
                        <div class="row">
                            <div class="col-md-7">
                                <h6 class="font-weight-bold text-dark mb-2">Key Setup Milestones:</h6>
                                <ul class="tour-checklist">
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Quick-Add Attributes:</strong> Inline [+] creation for Brands, Categories, and Units.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Smart Margin Engine:</strong> Auto-computes sale price from cost + percentage margin.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Composition & Recipe:</strong> Track sub-assembly parts and production wastage.</li>
                                </ul>
                            </div>
                            <div class="col-md-5">
                                <div class="tour-action-card">
                                    <div class="tour-action-label">Quick Destination</div>
                                    <p class="small text-muted mb-2">Add your first product or bulk import your existing price list.</p>
                                    <a href="{{ route('products.index') }}" class="btn btn-sm btn-block btn-success">
                                        <i class="fa fa-tag mr-1"></i> Explore Product Catalog
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Parties (Customers & Suppliers) -->
                <div class="zolo-tour-slide" data-slide="4">
                    <div class="tour-slide-hero theme-indigo">
                        <div class="tour-hero-icon"><i class="fa fa-users"></i></div>
                        <div class="tour-hero-content">
                            <span class="tour-step-pill">Stakeholder Directory</span>
                            <h3 class="tour-hero-title">4. Customers, Groups & Supplier Vendors</h3>
                            <p class="tour-hero-desc">Maintain comprehensive party master records with billing addresses, GSTIN numbers, customer group pricing tiers, and opening dues.</p>
                        </div>
                    </div>
                    <div class="tour-slide-details">
                        <div class="row">
                            <div class="col-md-7">
                                <h6 class="font-weight-bold text-dark mb-2">Key Setup Milestones:</h6>
                                <ul class="tour-checklist">
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Customer Groups:</strong> Categorize retail, wholesale, and distributor clients with dedicated discount policies.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Print Name / Display Name:</strong> Print specialized legal entity names on vouchers.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Due Ledger:</strong> 1-click access to party-wise receivables and payables history.</li>
                                </ul>
                            </div>
                            <div class="col-md-5">
                                <div class="tour-action-card">
                                    <div class="tour-action-label">Quick Destination</div>
                                    <p class="small text-muted mb-2">View customers, create customer groups, or add vendor suppliers.</p>
                                    <a href="{{ route('customer.index') }}" class="btn btn-sm btn-block btn-primary">
                                        <i class="fa fa-user-plus mr-1"></i> Manage Customers
                                    </a>
                                    <a href="{{ route('supplier.index') }}" class="btn btn-sm btn-block btn-outline-secondary mt-1">
                                        <i class="fa fa-truck mr-1"></i> Manage Suppliers
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: Invoicing & Purchases -->
                <div class="zolo-tour-slide" data-slide="5">
                    <div class="tour-slide-hero theme-amber">
                        <div class="tour-hero-icon"><i class="fa fa-file-text-o"></i></div>
                        <div class="tour-hero-content">
                            <span class="tour-step-pill">Operational Billing</span>
                            <h3 class="tour-hero-title">5. Sales Invoicing, Purchase Bills & GRNs</h3>
                            <p class="tour-hero-desc">Execute rapid point-of-sale or wholesale invoicing, issue Goods Receipt Notes (GRN), and preview bills with beautiful in-place modal cards.</p>
                        </div>
                    </div>
                    <div class="tour-slide-details">
                        <div class="row">
                            <div class="col-md-7">
                                <h6 class="font-weight-bold text-dark mb-2">Key Setup Milestones:</h6>
                                <ul class="tour-checklist">
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Real-time Stock Deductions:</strong> Invoices automatically adjust warehouse bins.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>In-Place Voucher View:</strong> Inspect line items, taxes, and payments with direct edit options.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Goods Receipt Notes (GRN):</strong> Accurately track received vs ordered purchase quantities.</li>
                                </ul>
                            </div>
                            <div class="col-md-5">
                                <div class="tour-action-card">
                                    <div class="tour-action-label">Quick Destination</div>
                                    <p class="small text-muted mb-2">Generate your first sales invoice or post an incoming purchase order.</p>
                                    <a href="{{ route('sales.index') }}" class="btn btn-sm btn-block btn-warning text-white">
                                        <i class="fa fa-shopping-cart mr-1"></i> Open Sales Desk
                                    </a>
                                    <a href="{{ route('purchases.index') }}" class="btn btn-sm btn-block btn-outline-secondary mt-1">
                                        <i class="fa fa-inbox mr-1"></i> Open Purchases Desk
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 6: Centralized Due Management -->
                <div class="zolo-tour-slide" data-slide="6">
                    <div class="tour-slide-hero theme-rose">
                        <div class="tour-hero-icon"><i class="fa fa-money"></i></div>
                        <div class="tour-hero-content">
                            <span class="tour-step-pill">Financial Command Center</span>
                            <h3 class="tour-hero-title">6. Centralized Due & Payment Command Center</h3>
                            <p class="tour-hero-desc">Monitor cash flows, collect customer receivables, disburse vendor payables, and clear debts with intelligent FIFO batch settlements.</p>
                        </div>
                    </div>
                    <div class="tour-slide-details">
                        <div class="row">
                            <div class="col-md-7">
                                <h6 class="font-weight-bold text-dark mb-2">Key Setup Milestones:</h6>
                                <ul class="tour-checklist">
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Unified Hub:</strong> Customer receivables, supplier payables, and expense dues in one place.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>FIFO Batch Settle:</strong> Clear oldest outstanding bills automatically when collecting lump sums.</li>
                                    <li><i class="fa fa-check-circle text-success mr-2"></i><strong>Instant Receipts:</strong> Auto-generate printable payment vouchers and bank ledger postings.</li>
                                </ul>
                            </div>
                            <div class="col-md-5">
                                <div class="tour-action-card">
                                    <div class="tour-action-label">Quick Destination</div>
                                    <p class="small text-muted mb-2">Inspect open dues across all customers and suppliers in real time.</p>
                                    <a href="{{ route('dues.index') }}" class="btn btn-sm btn-block btn-danger">
                                        <i class="fa fa-bolt mr-1"></i> Due Command Center
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Controls -->
            <div class="zolo-tour-footer">
                <div class="zolo-tour-footer-left">
                    <label class="tour-remember-checkbox mb-0">
                        <input type="checkbox" id="chk-dismiss-guided-tour">
                        <span>Don't show automatically on next login</span>
                    </label>
                </div>
                <div class="zolo-tour-footer-right">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-tour-prev" style="display: none;">
                        <i class="fa fa-angle-left mr-1"></i> Previous
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-tour-next">
                        Next Step <i class="fa fa-angle-right ml-1"></i>
                    </button>
                    <button type="button" class="btn btn-success btn-sm" id="btn-tour-finish" style="display: none;" data-dismiss="modal">
                        <i class="fa fa-check mr-1"></i> Complete Tour
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Guided Tour Styles */
.zolo-tour-dialog {
    max-width: 820px;
    margin: 2.5rem auto;
}
.zolo-tour-card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
    border: 1px solid #e2e8f0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.zolo-tour-header {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.zolo-tour-header-info {
    display: flex;
    align-items: center;
    gap: 14px;
}
.zolo-tour-logo-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35);
}
.zolo-tour-title {
    font-size: 16px;
    font-weight: 800;
    color: #0f172a;
    margin: 0;
}
.zolo-tour-subtitle {
    font-size: 12.5px;
    color: #64748b;
}
.zolo-tour-header-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}
.zolo-tour-step-counter {
    background: #ede9fe;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
    padding: 3px 10px;
    border-radius: 9999px;
    font-size: 11.5px;
    font-weight: 700;
}
.zolo-tour-close-btn {
    background: none;
    border: none;
    font-size: 18px;
    color: #94a3b8;
    cursor: pointer;
    line-height: 1;
    padding: 4px;
}
.zolo-tour-close-btn:hover { color: #0f172a; }

/* Progress Rail */
.zolo-tour-rail {
    background: #ffffff;
    border-bottom: 1px solid #f1f5f9;
    padding: 12px 24px;
    position: relative;
}
.zolo-tour-progress-bar-wrap {
    height: 4px;
    background: #f1f5f9;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 10px;
}
.zolo-tour-progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #7c3aed 0%, #10b981 100%);
    transition: width 0.3s ease;
}
.zolo-tour-rail-steps {
    display: flex;
    justify-content: space-between;
    gap: 6px;
}
.tour-rail-step {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 5px 10px;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    transition: all 0.2s ease;
}
.tour-rail-step .step-num {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 700;
}
.tour-rail-step.active {
    background: #fdf4ff;
    border-color: #f0abfc;
    color: #7c3aed;
    box-shadow: 0 2px 8px rgba(124, 58, 237, 0.15);
}
.tour-rail-step.active .step-num {
    background: #7c3aed;
    color: #ffffff;
}
.tour-rail-step.completed {
    background: #ecfdf5;
    border-color: #a7f3d0;
    color: #059669;
}
.tour-rail-step.completed .step-num {
    background: #059669;
    color: #ffffff;
}

/* Body / Hero */
.zolo-tour-body {
    padding: 24px;
    min-height: 380px;
}
.zolo-tour-slide {
    display: none;
    animation: fadeIn 0.25s ease;
}
.zolo-tour-slide.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
.tour-slide-hero {
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 18px;
    margin-bottom: 20px;
}
.tour-slide-hero.theme-purple { background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border: 1px solid #e9d5ff; }
.tour-slide-hero.theme-sky { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; }
.tour-slide-hero.theme-emerald { background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%); border: 1px solid #a7f3d0; }
.tour-slide-hero.theme-indigo { background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%); border: 1px solid #c7d2fe; }
.tour-slide-hero.theme-amber { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fde68a; }
.tour-slide-hero.theme-rose { background: linear-gradient(135deg, #fff1f2 0%, #ffe4e6 100%); border: 1px solid #fecdd3; }

.tour-hero-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.theme-purple .tour-hero-icon { color: #7c3aed; }
.theme-sky .tour-hero-icon { color: #0284c7; }
.theme-emerald .tour-hero-icon { color: #059669; }
.theme-indigo .tour-hero-icon { color: #4338ca; }
.theme-amber .tour-hero-icon { color: #d97706; }
.theme-rose .tour-hero-icon { color: #e11d48; }

.tour-step-pill {
    display: inline-block;
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 2px 8px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.7);
    margin-bottom: 6px;
}
.theme-purple .tour-step-pill { color: #7c3aed; }
.theme-sky .tour-step-pill { color: #0284c7; }
.theme-emerald .tour-step-pill { color: #059669; }
.theme-indigo .tour-step-pill { color: #4338ca; }
.theme-amber .tour-step-pill { color: #d97706; }
.theme-rose .tour-step-pill { color: #e11d48; }

.tour-hero-title {
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 6px 0;
}
.tour-hero-desc {
    font-size: 13.5px;
    color: #475569;
    margin: 0;
    line-height: 1.5;
}
.tour-checklist {
    list-style: none;
    padding-left: 0;
    margin: 0;
}
.tour-checklist li {
    font-size: 13px;
    color: #334155;
    margin-bottom: 8px;
    line-height: 1.45;
}
.tour-action-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px;
    text-align: center;
}
.tour-action-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 4px;
}

/* Footer */
.zolo-tour-footer {
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.tour-remember-checkbox {
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 6px;
    cursor: pointer;
}
.zolo-tour-footer-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Topbar Guided Tour Launcher Button */
.topbar-guided-tour-btn {
    background: #fdf4ff !important;
    color: #7c3aed !important;
    border: 1px solid #f0abfc !important;
    font-weight: 700;
    transition: all 0.2s ease;
}
.topbar-guided-tour-btn:hover {
    background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 4px 10px rgba(124, 58, 237, 0.25);
    border-color: transparent !important;
}

/* Dark Mode */
body.dark-mode .zolo-tour-card {
    background: #0f172a;
    border-color: #334155;
}
body.dark-mode .zolo-tour-header,
body.dark-mode .zolo-tour-footer,
body.dark-mode .zolo-tour-rail {
    background: #1e293b;
    border-color: #334155;
}
body.dark-mode .zolo-tour-title,
body.dark-mode .tour-hero-title {
    color: #f8fafc;
}
body.dark-mode .zolo-tour-subtitle,
body.dark-mode .tour-checklist li {
    color: #94a3b8;
}
body.dark-mode .tour-action-card {
    background: #1e293b;
    border-color: #334155;
}
body.dark-mode .tour-slide-hero.theme-purple { background: #2e1065; border-color: #581c87; }
body.dark-mode .tour-slide-hero.theme-sky { background: #082f49; border-color: #0369a1; }
body.dark-mode .tour-slide-hero.theme-emerald { background: #064e3b; border-color: #047857; }
body.dark-mode .tour-slide-hero.theme-indigo { background: #1e1b4b; border-color: #3730a3; }
body.dark-mode .tour-slide-hero.theme-amber { background: #451a03; border-color: #78350f; }
body.dark-mode .tour-slide-hero.theme-rose { background: #4c0519; border-color: #881337; }
body.dark-mode .tour-rail-step { background: #1e293b; border-color: #334155; color: #94a3b8; }
body.dark-mode .topbar-guided-tour-btn { background: #2e1065 !important; color: #ddd6fe !important; border-color: #581c87 !important; }
</style>

<script>
(function() {
    var totalSteps = 6;
    var currentStep = 1;

    function showStep(step) {
        if (step < 1) step = 1;
        if (step > totalSteps) step = totalSteps;
        currentStep = step;

        $('#tour-step-counter').text('Step ' + currentStep + ' of ' + totalSteps);
        var progressPct = (currentStep / totalSteps) * 100;
        $('#tour-progress-fill').css('width', progressPct + '%');

        $('.zolo-tour-slide').removeClass('active');
        $('.zolo-tour-slide[data-slide="' + currentStep + '"]').addClass('active');

        $('.tour-rail-step').each(function() {
            var s = parseInt($(this).attr('data-step'));
            $(this).removeClass('active completed');
            if (s === currentStep) {
                $(this).addClass('active');
            } else if (s < currentStep) {
                $(this).addClass('completed');
            }
        });

        if (currentStep === 1) {
            $('#btn-tour-prev').hide();
        } else {
            $('#btn-tour-prev').show();
        }

        if (currentStep === totalSteps) {
            $('#btn-tour-next').hide();
            $('#btn-tour-finish').show();
        } else {
            $('#btn-tour-next').show();
            $('#btn-tour-finish').hide();
        }
    }

    $(document).ready(function() {
        $('#btn-tour-next').on('click', function() {
            showStep(currentStep + 1);
        });

        $('#btn-tour-prev').on('click', function() {
            showStep(currentStep - 1);
        });

        $('.tour-rail-step').on('click', function() {
            var target = parseInt($(this).attr('data-step'));
            showStep(target);
        });

        $('#btn-tour-finish').on('click', function() {
            localStorage.setItem('zolo_guided_tour_dismissed', '1');
            $('#zolo-guided-tour-modal').modal('hide');
        });

        $('#chk-dismiss-guided-tour').on('change', function() {
            if ($(this).is(':checked')) {
                localStorage.setItem('zolo_guided_tour_dismissed', '1');
            } else {
                localStorage.removeItem('zolo_guided_tour_dismissed');
            }
        });

        $(document).on('click', '#btn-open-guided-tour', function(e) {
            e.preventDefault();
            showStep(1);
            $('#zolo-guided-tour-modal').modal('show');
        });

        // Auto-show on first visit if not dismissed
        var dismissed = localStorage.getItem('zolo_guided_tour_dismissed');
        if (!dismissed) {
            setTimeout(function() {
                // Only show if user is on dashboard or workspace
                if (window.location.pathname.indexOf('/dashboard') !== -1 || window.location.pathname.indexOf('/workspace') !== -1 || window.location.pathname === '/') {
                    showStep(1);
                    $('#zolo-guided-tour-modal').modal('show');
                }
            }, 1200);
        }
    });
})();
</script>

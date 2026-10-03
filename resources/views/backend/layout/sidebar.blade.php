@php
    $role = $role ?? (\Spatie\Permission\Models\Role::find(Auth::user()->role_id ?? 1));
    $isAdmin = Auth::check() && (Auth::user()->role_id == 1 || (isset($role) && $role->name == 'Admin'));
@endphp

<ul id="side-main-menu" class="side-menu list-unstyled d-print-none">
    <!-- SECTION: CORE -->
    <li class="sidebar-heading"><span>Core</span></li>
    <li id="dashboard-menu">
        <a href="{{url('/dashboard')}}">
            <i class="dripicons-meter"></i>
            <span>{{__('db.dashboard')}}</span>
        </a>
    </li>

    <!-- SECTION: COMMERCIAL & INVENTORY -->
    <li class="sidebar-heading"><span>Commercial &amp; Inventory</span></li>

    {{-- Product Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_product'))
        <li>
            <a href="#product" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-list"></i>
                <span>{{__('db.product')}}</span>
            </a>
            <ul id="product" class="collapse list-unstyled">
                @if($isAdmin || Auth::user()->can('categories-index'))
                    <li id="category-menu"><a href="{{route('category.index')}}">{{__('db.category')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('brand'))
                    <li id="brand-menu"><a href="{{route('brand.index')}}">{{__('db.Brand')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('unit'))
                    <li id="unit-menu"><a href="{{route('unit.index')}}">{{__('db.Unit')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('products-index'))
                    <li id="product-list-menu"><a href="{{route('products.index')}}">{{__('db.product_list')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('products-add'))
                    <li id="product-create-menu"><a href="{{route('products.create')}}">{{__('db.add_product')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('print_barcode'))
                    <li id="printBarcode-menu"><a href="{{route('product.printBarcode')}}">{{__('db.print_barcode')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('adjustment'))
                    <li id="adjustment-list-menu"><a href="{{route('qty_adjustment.index')}}">{{__('db.Adjustment List')}}</a></li>
                    <li id="adjustment-create-menu"><a href="{{route('qty_adjustment.create')}}">{{__('db.Add Adjustment')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('stock_count'))
                    <li id="stock-count-menu"><a href="{{route('stock-count.index')}}">{{__('db.Stock Count')}}</a></li>
                @endif
                <li id="damage-stock-menu"><a href="{{route('damage-stock.index')}}">Damage Stock Audit</a></li>
            </ul>
        </li>
    @endif

    {{-- Purchase Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_purchase'))
        <li>
            <a href="#purchase" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-card"></i>
                <span>{{__('db.Purchase')}}</span>
            </a>
            <ul id="purchase" class="collapse list-unstyled">
                @if($isAdmin || Auth::user()->can('purchases-index'))
                    <li id="purchase-list-menu"><a href="{{route('purchases.index')}}">{{__('db.Purchase List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('purchases-add'))
                    <li id="purchase-create-menu"><a href="{{route('purchases.create')}}">{{__('db.Add Purchase')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('purchases-import'))
                    <li id="purchase-import-menu"><a href="{{url('purchases/purchase_by_csv')}}">{{__('db.Import Purchase By CSV')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('purchase-return-index'))
                    <li id="purchase-return-menu"><a href="{{route('return-purchase.index')}}">{{__('db.Purchase Return')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    {{-- Sale Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_sale'))
        <li>
            <a href="#sale" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-cart"></i>
                <span>{{__('db.Sale')}}</span>
            </a>
            <ul id="sale" class="collapse list-unstyled">
                @if($isAdmin || Auth::user()->can('sales-index'))
                    <li id="sale-list-menu"><a href="{{route('sales.index')}}">{{__('db.Sale List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('sales-add'))
                    <li><a href="{{route('sale.pos')}}">POS Terminal</a></li>
                    <li id="sale-create-menu"><a href="{{route('sales.create')}}">{{__('db.Add Sale')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('sales-import'))
                    <li id="sale-import-menu"><a href="{{url('sales/sale_by_csv')}}">{{__('db.Import Sale By CSV')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('packing_slip_challan'))
                    <li id="packing-list-menu"><a href="{{route('packingSlip.index')}}">{{__('db.Packing Slip List')}}</a></li>
                    <li id="challan-list-menu"><a href="{{route('challan.index')}}">{{__('db.Challan List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('delivery'))
                    <li id="delivery-menu"><a href="{{route('delivery.index')}}">{{__('db.Delivery List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('gift_card'))
                    <li id="gift-card-menu"><a href="{{route('gift_cards.index')}}">{{__('db.Gift Card List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('coupon'))
                    <li id="coupon-menu"><a href="{{route('coupons.index')}}">{{__('db.Coupon List')}}</a></li>
                @endif
                <li id="courier-menu"><a href="{{route('couriers.index')}}">{{__('db.Courier List')}}</a></li>
                @if($isAdmin || Auth::user()->can('returns-index'))
                    <li id="sale-return-menu"><a href="{{route('return-sale.index')}}">{{__('db.Sale Return')}}</a></li>
                @endif
                <li id="sale-exchange-menu"><a href="{{route('exchange.index')}}">Product Exchange</a></li>
                <li id="installment-menu"><a href="{{route('installmentplan.index')}}">Installment Plans</a></li>
            </ul>
        </li>
    @endif

    {{-- Quotation Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_quotation'))
        <li>
            <a href="#quotation" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-document"></i>
                <span>{{__('db.Quotation')}}</span>
            </a>
            <ul id="quotation" class="collapse list-unstyled">
                @if($isAdmin || Auth::user()->can('quotes-index'))
                    <li id="quotation-list-menu"><a href="{{route('quotations.index')}}">{{__('db.Quotation List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('quotes-add'))
                    <li id="quotation-create-menu"><a href="{{route('quotations.create')}}">{{__('db.Add Quotation')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    {{-- Transfer Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_transfer'))
        <li>
            <a href="#transfer" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-export"></i>
                <span>{{__('db.Transfer')}}</span>
            </a>
            <ul id="transfer" class="collapse list-unstyled">
                @if($isAdmin || Auth::user()->can('transfers-index'))
                    <li id="transfer-list-menu"><a href="{{route('transfers.index')}}">{{__('db.Transfer List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('transfers-add'))
                    <li id="transfer-create-menu"><a href="{{route('transfers.create')}}">{{__('db.Add Transfer')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('transfers-import'))
                    <li id="transfer-import-menu"><a href="{{url('transfers/transfer_by_csv')}}">{{__('db.Import Transfer By CSV')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    {{-- Catalogue QR & Bookings --}}
    <li>
        <a href="{{route('qr.index')}}">
            <i class="dripicons-view-thumb"></i>
            <span>Catalogue QR</span>
        </a>
    </li>
    <li>
        <a href="{{route('bookings.calendar')}}">
            <i class="dripicons-calendar"></i>
            <span>Bookings Calendar</span>
        </a>
    </li>

    <!-- SECTION: FINANCE & DOUBLE-ENTRY LEDGER -->
    <li class="sidebar-heading"><span>Finance &amp; Ledger</span></li>

    {{-- Accounting (Double Entry) Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_accounting'))
        <li>
            <a href="#account" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-briefcase"></i>
                <span>{{__('db.Accounting')}}</span>
            </a>
            <ul id="account" class="collapse list-unstyled">
                <li id="coa-menu"><a href="{{route('accounting.coa')}}">Chart of Accounts</a></li>
                <li id="journal-menu"><a href="{{route('accounting.journal-entries')}}">Journal Entries</a></li>
                <li id="general-ledger-menu"><a href="{{route('accounting.general-ledger')}}">General Ledger</a></li>
                <li id="trial-balance-menu"><a href="{{route('accounting.trial-balance')}}">Trial Balance</a></li>
                <li id="profit-loss-menu"><a href="{{route('accounting.profit-loss')}}">Profit &amp; Loss</a></li>
                <li id="balance-sheet-menu"><a href="{{route('accounting.balance-sheet')}}">Balance Sheet</a></li>
                <li id="cash-flow-menu"><a href="{{route('accounting.cash-flow')}}">Cash Flow Statement</a></li>
                <li id="inv-close-menu"><a href="{{route('accounting.inventory-close')}}">Periodic Inventory Close</a></li>
                <li id="semantic-map-menu"><a href="{{route('accounting.semantic-mappings')}}">Semantic Account Mappings</a></li>
                @if($isAdmin || Auth::user()->can('account-index'))
                    <li id="account-list-menu"><a href="{{route('accounts.index')}}">{{__('db.Account List')}} (Cash)</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('money-transfer'))
                    <li id="money-transfer-menu"><a href="{{route('money-transfers.index')}}">{{__('db.Money Transfer')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    {{-- Expense Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_expense'))
        <li>
            <a href="#expense" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-wallet"></i>
                <span>{{__('db.Expense')}}</span>
            </a>
            <ul id="expense" class="collapse list-unstyled">
                <li id="exp-cat-menu"><a href="{{route('expense_categories.index')}}">{{__('db.Expense Category')}}</a></li>
                @if($isAdmin || Auth::user()->can('expenses-index'))
                    <li id="exp-list-menu"><a href="{{route('expenses.index')}}">{{__('db.Expense List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('expenses-add'))
                    <li><a id="add-expense" href="">{{__('db.Add Expense')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    {{-- Income Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_income'))
        <li>
            <a href="#income" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-rocket"></i>
                <span>{{__('db.Income')}}</span>
            </a>
            <ul id="income" class="collapse list-unstyled">
                <li id="income-cat-menu"><a href="{{route('income_categories.index')}}">{{__('db.Income Category')}}</a></li>
                @if($isAdmin || Auth::user()->can('incomes-index'))
                    <li id="income-list-menu"><a href="{{route('incomes.index')}}">{{__('db.Income List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('incomes-add'))
                    <li><a id="add-income" href="">{{__('db.Add Income')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    <!-- SECTION: ORGANIZATION & OPERATIONS -->
    <li class="sidebar-heading"><span>Organization &amp; Operations</span></li>

    {{-- People Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_people'))
        <li>
            <a href="#people" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-user"></i>
                <span>{{__('db.People')}}</span>
            </a>
            <ul id="people" class="collapse list-unstyled">
                @if($isAdmin || Auth::user()->can('customers-index'))
                    <li id="customer-list-menu"><a href="{{route('customer.index')}}">{{__('db.Customer List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('suppliers-index'))
                    <li id="supplier-list-menu"><a href="{{route('supplier.index')}}">{{__('db.Supplier List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('users-index'))
                    <li id="user-list-menu"><a href="{{route('user.index')}}">{{__('db.User List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('sale-agents'))
                    <li id="sale-agent-menu"><a href="{{route('sale-agents.index')}}">{{__('db.Sale Agents')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('billers-index'))
                    <li id="biller-list-menu"><a href="{{route('biller.index')}}">{{__('db.Biller List')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    {{-- HRM Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_hrm'))
        <li>
            <a href="#hrm" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-user-group"></i>
                <span>{{__('db.HRM')}}</span>
            </a>
            <ul id="hrm" class="collapse list-unstyled">
                @if($isAdmin || Auth::user()->can('department'))
                    <li id="dept-menu"><a href="{{route('departments.index')}}">{{__('db.Department')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('designations'))
                    <li id="designations-menu"><a href="{{route('designations.index')}}">{{__('db.Designation')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('shift'))
                    <li id="shift-menu"><a href="{{route('shift.index')}}">{{__('db.Shift')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('employees-index'))
                    <li id="employee-menu"><a href="{{route('employees.index')}}">{{__('db.Employee')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('attendance'))
                    <li id="attendance-menu"><a href="{{route('attendance.index')}}">{{__('db.Attendance')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('holiday'))
                    <li id="holiday-menu"><a href="{{route('holidays.index')}}">{{__('db.Holiday')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('overtime'))
                    <li id="overtime-menu"><a href="{{route('overtime.index')}}">{{__('db.Overtime')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('leave-type'))
                    <li id="leave-type-menu"><a href="{{route('leave-type.index')}}">{{__('db.Leave Type')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('leave'))
                    <li id="leave-menu"><a href="{{route('leave.index')}}">{{__('db.Leaves')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('payroll'))
                    <li id="payroll-menu"><a href="{{route('payroll.index')}}">{{__('db.Payroll')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    <!-- SECTION: INDUSTRY ADDONS & MODULAR SOLUTIONS -->
    <li class="sidebar-heading"><span>Industry Addons</span></li>

    {{-- Water Logistics ("DK Track") --}}
    @if(in_array('water_logistics', explode(',', $general_setting->modules ?? '')))
        <li>
            <a href="#water-logistics-menu" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-drop"></i>
                <span>Water Logistics (DK)</span>
            </a>
            <ul id="water-logistics-menu" class="collapse list-unstyled">
                <li><a href="{{route('water-logistics.index')}}">Fleet Trips &amp; Can Routes</a></li>
                <li><a href="{{route('water-logistics.credit-aging')}}">Corporate Credit Aging</a></li>
            </ul>
        </li>
    @endif

    {{-- Bakery & Cafe POS --}}
    @if(in_array('cafe_bakery', explode(',', $general_setting->modules ?? '')))
        <li>
            <a href="#cafe-menu" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-store"></i>
                <span>Bakery &amp; Cafe POS</span>
            </a>
            <ul id="cafe-menu" class="collapse list-unstyled">
                <li><a href="{{route('cafe.index')}}">Operations Dashboard</a></li>
                <li><a href="{{route('cafe.pos')}}">Touch POS Terminal</a></li>
            </ul>
        </li>
    @endif

    {{-- Repair & Service Center --}}
    @if(in_array('repair', explode(',', $general_setting->modules ?? '')))
        <li>
            <a href="#repair-menu" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-wrench"></i>
                <span>Repair Center</span>
            </a>
            <ul id="repair-menu" class="collapse list-unstyled">
                <li><a href="{{route('repair.dashboard')}}">Repair Dashboard</a></li>
                <li><a href="{{route('repair.services')}}">Service Job Sheets</a></li>
                <li><a href="{{route('repair.device-types')}}">Device Categories</a></li>
            </ul>
        </li>
    @endif

    {{-- Project Management --}}
    @if(in_array('project_management', explode(',', $general_setting->modules ?? '')))
        <li>
            <a href="#project-mgmt-menu" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-briefcase"></i>
                <span>Project Management</span>
            </a>
            <ul id="project-mgmt-menu" class="collapse list-unstyled">
                <li><a href="{{route('project-management.projects')}}">Projects List</a></li>
                <li><a href="{{route('project-management.tasks')}}">Task Board</a></li>
                <li><a href="{{route('project-management.categories')}}">Project Categories</a></li>
            </ul>
        </li>
    @endif

    {{-- Manufacturing --}}
    @if(in_array('manufacturing', explode(',', $general_setting->modules ?? '')))
        <li>
            <a href="#manufacturing" aria-expanded="false" data-toggle="collapse">
                <i class="fa fa-industry"></i>
                <span>{{__('db.Manufacturing')}}</span>
            </a>
            <ul id="manufacturing" class="collapse list-unstyled">
                <li id="production-list-menu"><a href="{{route('productions.index')}}">{{__('db.Production List')}}</a></li>
                <li id="production-create-menu"><a href="{{route('productions.create')}}">{{__('db.Add Production')}}</a></li>
                <li id="recipe-menu"><a href="{{route('recipes.index')}}">{{__('db.Recipe')}}</a></li>
            </ul>
        </li>
    @endif

    <!-- SECTION: ANALYTICS & INTELLIGENCE -->
    <li class="sidebar-heading"><span>Analytics &amp; Intelligence</span></li>

    {{-- Reports Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_reports'))
        <li>
            <a href="#report" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-document-remove"></i>
                <span>{{__('db.Reports')}}</span>
            </a>
            <ul id="report" class="collapse list-unstyled">
                @if($isAdmin || $role->id <= 2)
                    <li id="activity-log-menu"><a href="{{route('setting.activityLog')}}">{{__('db.Activity Log')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('profit-loss'))
                    <li id="profit-loss-report-menu">
                        {!! Form::open(['route' => 'report.profitLoss', 'method' => 'post', 'id' => 'profitLoss-report-form']) !!}
                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                            <a id="profitLoss-link" href="">{{__('db.Summary Report')}}</a>
                        {!! Form::close() !!}
                    </li>
                @endif
                @if($isAdmin || Auth::user()->can('best-seller'))
                    <li id="best-seller-report-menu"><a href="{{url('report/best_seller')}}">{{__('db.Best Seller')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('product-report'))
                    <li id="product-report-menu">
                        {!! Form::open(['route' => 'report.product', 'method' => 'get', 'id' => 'product-report-form']) !!}
                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                            <input type="hidden" name="warehouse_id" value="0" />
                            <a id="report-link" href="">{{__('db.Product Report')}}</a>
                        {!! Form::close() !!}
                    </li>
                @endif
                @if($isAdmin || Auth::user()->can('daily-sale'))
                    <li id="daily-sale-report-menu"><a href="{{url('report/daily_sale/'.date('Y').'/'.date('m'))}}">{{__('db.Daily Sale')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('monthly-sale'))
                    <li id="monthly-sale-report-menu"><a href="{{url('report/monthly_sale/'.date('Y'))}}">{{__('db.Monthly Sale')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('daily-purchase'))
                    <li id="daily-purchase-report-menu"><a href="{{url('report/daily_purchase/'.date('Y').'/'.date('m'))}}">{{__('db.Daily Purchase')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('monthly-purchase'))
                    <li id="monthly-purchase-report-menu"><a href="{{url('report/monthly_purchase/'.date('Y'))}}">{{__('db.Monthly Purchase')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('sale-report'))
                    <li id="sale-report-menu">
                        {!! Form::open(['route' => 'report.sale', 'method' => 'post', 'id' => 'sale-report-form']) !!}
                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                            <input type="hidden" name="warehouse_id" value="0" />
                            <a id="sale-report-link" href="">{{__('db.Sale Report')}}</a>
                        {!! Form::close() !!}
                    </li>
                @endif
                <li id="challan-report-menu"><a href="{{route('report.challan')}}">{{__('db.Challan Report')}}</a></li>
                @if($isAdmin || Auth::user()->can('sale-report-chart'))
                    <li id="sale-report-chart-menu">
                        {!! Form::open(['route' => 'report.saleChart', 'method' => 'post', 'id' => 'sale-report-chart-form']) !!}
                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                            <input type="hidden" name="warehouse_id" value="0" />
                            <input type="hidden" name="time_period" value="weekly" />
                            <a id="sale-report-chart-link" href="">{{__('db.Sale Report Chart')}}</a>
                        {!! Form::close() !!}
                    </li>
                @endif
                @if($isAdmin || Auth::user()->can('payment-report'))
                    <li id="payment-report-menu">
                        {!! Form::open(['route' => 'report.paymentByDate', 'method' => 'post', 'id' => 'payment-report-form']) !!}
                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                            <a id="payment-report-link" href="">{{__('db.Payment Report')}}</a>
                        {!! Form::close() !!}
                    </li>
                @endif
                @if($isAdmin || Auth::user()->can('purchase-report'))
                    <li id="purchase-report-menu">
                        {!! Form::open(['route' => 'report.purchase', 'method' => 'post', 'id' => 'purchase-report-form']) !!}
                            <input type="hidden" name="start_date" value="{{date('Y-m').'-'.'01'}}" />
                            <input type="hidden" name="end_date" value="{{date('Y-m-d')}}" />
                            <input type="hidden" name="warehouse_id" value="0" />
                            <a id="purchase-report-link" href="">{{__('db.Purchase Report')}}</a>
                        {!! Form::close() !!}
                    </li>
                @endif
                @if($isAdmin || Auth::user()->can('warehouse-report'))
                    <li id="warehouse-report-menu"><a id="warehouse-report-link" href="">{{__('db.Warehouse Report')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('warehouse-stock-report'))
                    <li id="warehouse-stock-report-menu"><a href="{{route('report.warehouseStock')}}">{{__('db.Warehouse Stock Chart')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('product-expiry-report'))
                    <li id="productExpiry-report-menu"><a href="{{route('report.productExpiry')}}">{{__('db.Product Expiry Report')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('product-qty-alert'))
                    <li id="qtyAlert-report-menu"><a href="{{route('report.qtyAlert')}}">{{__('db.Product Quantity Alert')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('dso-report'))
                    <li id="daily-sale-objective-menu"><a href="{{route('report.dailySaleObjective')}}">{{__('db.Daily Sale Objective Report')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('user-report'))
                    <li id="user-report-menu"><a id="user-report-link" href="">{{__('db.User Report')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('biller-report'))
                    <li id="biller-report-menu"><a id="biller-report-link" href="">{{__('db.Biller Report')}}</a></li>
                @endif
                <li id="cash-register-report-menu"><a href="{{route('cashRegister.index')}}">{{__('db.Cash Register')}}</a></li>
            </ul>
        </li>
    @endif

    <!-- SECTION: SYSTEM & SETTINGS -->
    <li class="sidebar-heading"><span>System &amp; Settings</span></li>

    {{-- WhatsApp Menu --}}
    <li>
        <a href="#whatsapp" aria-expanded="false" data-toggle="collapse">
            <i class="dripicons-message"></i>
            <span>{{ __('db.whatsapp') }}</span>
        </a>
        <ul id="whatsapp" class="collapse list-unstyled">
            <li id="whatsapp-settings-menu"><a href="{{ route('whatsapp.settings') }}">{{ __('db.whatsapp_settings') }}</a></li>
            <li id="whatsapp-templates-menu"><a href="{{ route('whatsapp.templates') }}">{{ __('db.message_templates') }}</a></li>
            <li id="whatsapp-send-menu"><a href="{{ route('whatsapp.send.page') }}">{{ __('db.send_message') }}</a></li>
        </ul>
    </li>

    {{-- Settings Menu --}}
    @if($isAdmin || Auth::user()->can('sidebar_settings'))
        <li>
            <a href="#setting" aria-expanded="false" data-toggle="collapse">
                <i class="dripicons-gear"></i>
                <span>{{__('db.settings')}}</span>
            </a>
            <ul id="setting" class="collapse list-unstyled">
                @if($isAdmin || \Auth::user()->role_id <= 2)
                    <li id="printer-menu"><a href="{{route('printers.index')}}">{{__('db.Receipt Printers')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('invoice_setting'))
                    <li id="invoice-menu"><a href="{{route('settings.invoice.index')}}">{{__('db.Invoice Settings')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('role_permission'))
                    <li id="role-menu"><a href="{{route('role.index')}}">{{__('db.Role Permission')}}</a></li>
                    <li><a href="{{route('smstemplates.index')}}">{{__('db.SMS Template')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('custom_field'))
                    <li id="custom-field-list-menu"><a href="{{route('custom-fields.index')}}">{{__('db.Custom Field List')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('discount_plan'))
                    <li id="discount-plan-list-menu"><a href="{{route('discount-plans.index')}}">{{__('db.Discount Plan')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('discount'))
                    <li id="discount-list-menu"><a href="{{route('discounts.index')}}">{{__('db.Discount')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('all_notification'))
                    <li id="notification-list-menu"><a href="{{route('notifications.index')}}">{{__('db.All Notification')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('send_notification'))
                    <li id="notification-menu"><a href="" id="send-notification">{{__('db.Send Notification')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('warehouse'))
                    <li id="warehouse-menu"><a href="{{route('warehouse.index')}}">{{__('db.Warehouse')}}</a></li>
                @endif
                @if($isAdmin || \Auth::user()->role_id <= 2)
                    <li id="table-menu"><a href="{{route('tables.index')}}">{{__('db.Tables')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('customer_group'))
                    <li id="customer-group-menu"><a href="{{route('customer_group.index')}}">{{__('db.Customer Group')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('currency'))
                    <li id="currency-menu"><a href="{{route('currency.index')}}">{{__('db.Currency')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('tax'))
                    <li id="tax-menu"><a href="{{route('tax.index')}}">{{__('db.Tax')}}</a></li>
                @endif
                <li id="user-menu"><a href="{{route('user.profile', ['id' => Auth::id()])}}">{{__('db.User Profile')}}</a></li>
                @if($isAdmin || Auth::user()->can('create_sms'))
                    <li id="create-sms-menu"><a href="{{route('setting.createSms')}}">{{__('db.Create SMS')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('backup_database'))
                    <li><a href="{{route('setting.backup')}}">{{__('db.Backup Database')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('general_setting'))
                    <li id="general-setting-menu"><a href="{{route('setting.general')}}">{{__('db.General Setting')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('mail_setting'))
                    <li id="mail-setting-menu"><a href="{{route('setting.mail')}}">{{__('db.Mail Setting')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('reward_point_setting'))
                    <li id="reward-point-setting-menu"><a href="{{route('setting.rewardPoint')}}">{{__('db.Reward Point Setting')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('sms_setting'))
                    <li id="sms-setting-menu"><a href="{{route('setting.sms')}}">{{__('db.SMS Setting')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('payment_gateway_setting'))
                    <li id="payment-gateway-setting-menu"><a href="{{route('setting.gateway')}}">{{__('db.Payment Gateways')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('pos_setting'))
                    <li id="pos-setting-menu"><a href="{{route('setting.pos')}}">POS {{__('db.settings')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('hrm_setting'))
                    <li id="hrm-setting-menu"><a href="{{route('setting.hrm')}}">{{__('db.HRM Setting')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('barcode_setting'))
                    <li id="barcode-setting-menu"><a href="{{route('barcodes.index')}}">{{__('db.Barcode Settings')}}</a></li>
                @endif
                @if($isAdmin || Auth::user()->can('language_setting'))
                    <li id="languages"><a href="{{route('languages')}}">{{__('db.Languages')}}</a></li>
                @endif
            </ul>
        </li>
    @endif

    {{-- Addons menu --}}
    @if($isAdmin || Auth::user()->can('addons'))
        @if(\Auth::user()->role_id != 5)
            @if(!config('database.connections.saleprosaas_landlord'))
                <li><a href="{{url('addon-list')}}" id="addon-list"><i class="dripicons-flag"></i><span>{{__('db.Addons')}}</span></a></li>
            @endif
            @if(in_array('woocommerce', explode(',', $general_setting->modules ?? '')) && Route::has('woocommerce.index'))
                <li><a href="{{route('woocommerce.index')}}"><i class="fa fa-wordpress"></i><span>WooCommerce</span></a></li>
            @endif
            @if(in_array('ecommerce', explode(',', $general_setting->modules ?? '')) && View::exists('ecommerce::backend.layout.sidebar-menu'))
                <li>
                    <a href="#ecommerce" aria-expanded="false" data-toggle="collapse"><i class="dripicons-shopping-bag"></i><span>eCommerce</span></a>
                    <ul id="ecommerce" class="collapse list-unstyled">
                        @include('ecommerce::backend.layout.sidebar-menu')
                    </ul>
                </li>
            @endif
            @if(in_array('project', explode(',', $general_setting->modules ?? '')) && View::exists('project::backend.layout.sidebar-menu'))
                @include('project::backend.layout.sidebar-menu')
            @endif
            @if(in_array('restaurant', explode(',', $general_setting->modules ?? '')) && View::exists('restaurant::backend.layout.sidebar-menu'))
                @include('restaurant::backend.layout.sidebar-menu')
            @endif
        @endif
    @endif
</ul>

@extends('backend.layout.main')

@section('content')

@push('css')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.9/css/intlTelInput.css"/>
<link rel="stylesheet" href="{{ versioned_asset('css/product-workspace.css') }}" type="text/css">
<link rel="stylesheet" href="{{ versioned_asset('css/pages/parties-workspace.css') }}" type="text/css">
<link rel="stylesheet" href="{{ versioned_asset('css/pages/shared-customer-create.css') }}">
@endpush

<x-error-message key="not_permitted" />

<x-party-command-bar active="customers" title="{{ __('db.customer') }} Command Center" badge="{{ __('db.Add Customer') }}">
    <a href="{{ route('customer.index') }}" class="btn btn-outline-secondary">
        <i class="dripicons-arrow-left"></i> {{ __('db.Back') }}
    </a>
</x-party-command-bar>

<div class="customer-workspace-view">
    <div class="party-single-card">
        <div class="party-card-header">
            <div class="party-card-title-group">
                <h4><i class="dripicons-user-plus text-primary"></i> {{ __('db.Add Customer') }}</h4>
                <div class="party-hint">{{ __('db.The field labels marked with * are required input fields') }}.</div>
            </div>
            <div class="party-card-actions">
                <a href="{{ route('customer.index') }}" class="btn btn-outline-secondary">
                    <i class="dripicons-cross"></i> {{ __('db.Cancel') }}
                </a>
                <button type="submit" form="customer_form" class="btn btn-primary">
                    <i class="dripicons-checkmark"></i> {{ __('db.submit') }}
                </button>
            </div>
        </div>

        <div class="party-card-body">
            {!! Form::open(['route' => 'customer.store', 'method' => 'post', 'files' => true, 'id' => 'customer_form']) !!}
            <input type="hidden" name="pos" value="0">

            <div class="party-form-grid">
                <!-- BOX 1: Primary Identity & Categorization -->
                <div class="party-section-box party-sec-identity">
                    <div class="party-section-header">
                        <i class="dripicons-user"></i> {{ __('db.Customer Details') }}
                    </div>
                    <div class="party-section-content">
                        <div class="party-fields-row">
                            <div class="party-form-group">
                                <label for="customer-group-id">{{ __('db.Customer Group') }} <span class="req">*</span> <a href="{{ route('customer_group.index') }}" target="_blank" class="text-primary small ml-2" title="{{ __('db.Add Customer Group') }}"><i class="dripicons-plus"></i> {{ __('db.Add Customer Group') }}</a></label>
                                <select required class="form-control selectpicker" id="customer-group-id" name="customer_group_id">
                                    @foreach($lims_customer_group_all as $customer_group)
                                        <option value="{{ $customer_group->id }}">{{ $customer_group->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="party-form-group">
                                <label for="name">{{ __('db.name') }} <span class="req">*</span></label>
                                <input type="text" id="name" name="customer_name" required class="form-control" placeholder="{{ __('db.Customer Name') }}">
                            </div>
                            <div class="party-form-group">
                                <label for="company_name">{{ __('db.Company Name') }} <span class="asterisk text-danger" style="display:none;">*</span></label>
                                <input type="text" id="company_name" name="company_name" class="form-control" placeholder="{{ __('db.Company Name') }}">
                                @if($errors->has('company_name'))
                                    <small class="text-danger">{{ $errors->first('company_name') }}</small>
                                @endif
                            </div>
                            <div class="party-form-group d-flex align-items-center">
                                <label class="party-toggle-box w-100 mb-0">
                                    <input type="checkbox" name="both" value="1" id="both-toggle">
                                    <div>
                                        <div class="party-toggle-label">{{ __('db.Both Customer and Supplier') }}</div>
                                        <div class="party-toggle-desc">Register this entity as both client and vendor</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOX 2: Contact Details -->
                <div class="party-section-box party-sec-contact">
                    <div class="party-section-header">
                        <i class="dripicons-phone"></i> Contact &amp; Communication
                    </div>
                    <div class="party-section-content">
                        <div class="party-fields-row">
                            <div class="party-form-group">
                                <label for="phone_number">{{ __('db.Phone Number') }} <span class="req">*</span></label>
                                <input type="text" id="phone_number" name="phone_number" required class="form-control" placeholder="Primary contact phone">
                                @if($errors->has('phone_number'))
                                    <small class="text-danger">{{ $errors->first('phone_number') }}</small>
                                @endif
                            </div>
                            <div class="party-form-group">
                                <label for="email">{{ __('db.Email') }} <span class="asterisk text-danger" style="display:none;">*</span></label>
                                <input type="email" id="email" name="email" placeholder="example@domain.com" class="form-control">
                                @if($errors->has('email'))
                                    <small class="text-danger">{{ $errors->first('email') }}</small>
                                @endif
                            </div>
                            <div class="party-form-group party-field-col-full country-phone-group">
                                <label for="wa_number">{{ __('db.WhatsApp Number') }}</label>
                                <div class="d-flex">
                                    <select id="country_code" name="country_code" class="form-control w-auto me-2"></select>
                                    <input type="tel" id="wa_number" class="form-control" placeholder="WhatsApp contact number">
                                    <input type="hidden" id="full_phone" name="wa_number">
                                </div>
                                <span class="party-field-hint">Used for dispatch slips, payment reminders and invoices</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOX 3: Location & GST Compliance -->
                <div class="party-section-box party-sec-address">
                    <div class="party-section-header">
                        <i class="dripicons-location"></i> Billing Address &amp; GSTIN
                    </div>
                    <div class="party-section-content">
                        <div class="party-fields-row">
                            <div class="party-form-group party-field-col-full">
                                <label for="tax_no">GSTIN / {{ __('db.Tax Number') }}</label>
                                <input type="text" id="tax_no" name="tax_no" class="form-control text-uppercase" placeholder="15-character GSTIN (autofills party &amp; address)">
                            </div>
                            <div class="party-form-group party-field-col-full">
                                <label for="address">{{ __('db.Address') }} <span class="asterisk text-danger" style="display:none;">*</span></label>
                                <input type="text" id="address" name="address" class="form-control" placeholder="Street address, building, premises">
                            </div>
                            <div class="party-form-group">
                                <label for="city">{{ __('db.City') }} <span class="asterisk text-danger" style="display:none;">*</span></label>
                                <input type="text" id="city" name="city" class="form-control" placeholder="City / Town">
                            </div>
                            <div class="party-form-group">
                                <label for="state">{{ __('db.State') }}</label>
                                <input type="text" id="state" name="state" class="form-control" placeholder="State name or code">
                            </div>
                            <div class="party-form-group">
                                <label for="postal_code">{{ __('db.Postal Code') }}</label>
                                <input type="text" id="postal_code" name="postal_code" class="form-control" placeholder="PIN code">
                            </div>
                            <div class="party-form-group">
                                <label for="country">{{ __('db.Country') }}</label>
                                <input type="text" id="country" name="country" value="India" class="form-control" placeholder="Country">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOX 4: Credit Terms & Balances -->
                <div class="party-section-box party-sec-credit">
                    <div class="party-section-header">
                        <i class="fa fa-money"></i> Credit Terms &amp; Accounts
                    </div>
                    <div class="party-section-content">
                        <div class="party-fields-row">
                            <div class="party-form-group">
                                <label for="opening_balance">
                                    {{ __('db.Opening balance') }} ({{ __('db.Due') }})
                                    <x-info title="Add Customer's old due amount here. Will post to Receivables." type="info" />
                                </label>
                                <input type="number" id="opening_balance" name="opening_balance" class="form-control" value="0" step="any" min="0">
                            </div>
                            <div class="party-form-group">
                                <label for="deposit">
                                    {{ __('db.Initial Deposit') }}
                                    <x-info title="Customer can pay with deposit amount you set here" type="info" />
                                </label>
                                <input type="number" id="deposit" name="deposit" value="0" class="form-control" step="0.01">
                            </div>
                            <div class="party-form-group">
                                <label for="credit_limit">
                                    {{ __('db.Credit Limit') }} ₹
                                    <x-info title="0 or blank for unlimited credit" type="info" />
                                </label>
                                <input type="number" id="credit_limit" name="credit_limit" class="form-control" value="0" step="any" min="0">
                            </div>
                            @if(config('commercial.enabled'))
                                <div class="party-form-group">
                                    <label for="credit-days">Credit Days <span class="party-field-hint d-inline">(sets due date)</span></label>
                                    <input id="credit-days" type="number" name="credit_days" class="form-control" min="0" max="3650" value="0">
                                </div>
                                <div class="party-form-group party-field-col-full">
                                    <label for="search-alias">Search Alias <span class="party-field-hint d-inline">(quick keyword for voucher desks)</span></label>
                                    <input id="search-alias" name="search_alias" class="form-control" maxlength="100" placeholder="e.g. AG-BUYER, TIR-01">
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- BOX 5: Custom Fields (if configured) -->
                @if(count($custom_fields) > 0)
                <div class="party-section-box party-sec-custom party-field-col-full">
                    <div class="party-section-header">
                        <i class="dripicons-view-thumb"></i> Custom Master Attributes
                    </div>
                    <div class="party-section-content">
                        <div class="party-fields-row">
                            @foreach($custom_fields as $field)
                                @if(!$field->is_admin || \Auth::user()->role_id == 1)
                                    <div class="party-form-group {{ $field->grid_value > 6 ? 'party-field-col-full' : '' }}">
                                        <label>{{ $field->name }} @if($field->is_required)<span class="req">*</span>@endif</label>
                                        @if($field->type == 'text')
                                            <input type="text" name="{{ str_replace(' ', '_', strtolower($field->name)) }}" value="{{ $field->default_value }}" class="form-control" @if($field->is_required) required @endif>
                                        @elseif($field->type == 'number')
                                            <input type="number" name="{{ str_replace(' ', '_', strtolower($field->name)) }}" value="{{ $field->default_value }}" class="form-control" @if($field->is_required) required @endif>
                                        @elseif($field->type == 'textarea')
                                            <textarea rows="3" name="{{ str_replace(' ', '_', strtolower($field->name)) }}" class="form-control" @if($field->is_required) required @endif>{{ $field->default_value }}</textarea>
                                        @elseif($field->type == 'checkbox')
                                            @php $option_values = explode(",", $field->option_value); @endphp
                                            <div class="d-flex flex-wrap gap-2 pt-1">
                                                @foreach($option_values as $value)
                                                    <label class="mr-3 font-weight-normal">
                                                        <input type="checkbox" name="{{ str_replace(' ', '_', strtolower($field->name)) }}[]" value="{{ $value }}" @if($value == $field->default_value) checked @endif> {{ $value }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif($field->type == 'radio_button')
                                            @php $option_values = explode(",", $field->option_value); @endphp
                                            <div class="d-flex flex-wrap gap-2 pt-1">
                                                @foreach($option_values as $value)
                                                    <label class="mr-3 font-weight-normal">
                                                        <input type="radio" name="{{ str_replace(' ', '_', strtolower($field->name)) }}" value="{{ $value }}" @if($value == $field->default_value) checked @endif> {{ $value }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif($field->type == 'select')
                                            @php $option_values = explode(",", $field->option_value); @endphp
                                            <select class="form-control selectpicker" name="{{ str_replace(' ', '_', strtolower($field->name)) }}" @if($field->is_required) required @endif>
                                                @foreach($option_values as $value)
                                                    <option value="{{ $value }}" @if($value == $field->default_value) selected @endif>{{ $value }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($field->type == 'multi_select')
                                            @php $option_values = explode(",", $field->option_value); @endphp
                                            <select class="form-control selectpicker" name="{{ str_replace(' ', '_', strtolower($field->name)) }}[]" @if($field->is_required) required @endif multiple>
                                                @foreach($option_values as $value)
                                                    <option value="{{ $value }}" @if($value == $field->default_value) selected @endif>{{ $value }}</option>
                                                @endforeach
                                            </select>
                                        @elseif($field->type == 'date_picker')
                                            <input type="text" name="{{ str_replace(' ', '_', strtolower($field->name)) }}" value="{{ $field->default_value }}" class="form-control date" @if($field->is_required) required @endif>
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- BOX 6: Portal & User Access -->
                <div class="party-section-box party-sec-user party-field-col-full">
                    <div class="party-section-header">
                        <i class="dripicons-lock"></i> Portal &amp; Login Access
                    </div>
                    <div class="party-section-content">
                        <div class="party-fields-row">
                            <div class="party-form-group party-field-col-full">
                                <label class="party-toggle-box">
                                    <input type="checkbox" name="user" value="1" id="user-toggle">
                                    <div>
                                        <div class="party-toggle-label">{{ __('db.Add User') }}</div>
                                        <div class="party-toggle-desc">Enable portal login credentials for this customer to view invoices &amp; statements</div>
                                    </div>
                                </label>
                            </div>
                            <div class="party-form-group user-input" style="display:none;">
                                <label for="username">{{ __('db.UserName') }} <span class="req">*</span></label>
                                <input type="text" id="username" name="name" class="form-control" placeholder="Customer username">
                                @if($errors->has('name'))
                                    <small class="text-danger">{{ $errors->first('name') }}</small>
                                @endif
                            </div>
                            <div class="party-form-group user-input" style="display:none;">
                                <label for="password">{{ __('db.Password') }} <span class="req">*</span></label>
                                <input type="password" id="password" name="password" class="form-control" placeholder="Portal password">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Actions -->
            <div class="party-form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="dripicons-checkmark"></i> {{ __('db.submit') }}
                </button>
                <a href="{{ route('customer.index') }}" class="btn btn-outline-secondary">
                    {{ __('db.Cancel') }}
                </a>
                <span class="party-shortcut-hint">* required fields · Enter or Tab moves to next field</span>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
<script>
    const input = document.querySelector("#wa_number");
    if (input) {
        window.intlTelInput(input, {
            initialCountry: "in",
            geoIpLookup: function (callback) {
                fetch("https://ipapi.co/json")
                    .then((res) => res.json())
                    .then((data) => callback(data.country_code))
                    .catch(() => callback("in"));
            },
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js"
        });

        $("#customer_form").submit(function(e) {
            e.preventDefault();
            var iti = window.intlTelInputGlobals.getInstance(input);
            if (iti) {
                var full_number = iti.getNumber();
                $('#full_phone').val(full_number);
            }
            $(this).off('submit').submit();
        });
    }
</script>

<script type="text/javascript">
    $("ul#parties").siblings('a').attr('aria-expanded','true');
    $("ul#parties").addClass("show");
    $("ul#parties #customer-list-menu").addClass("active");

    $('#both-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('.asterisk').show();
            $('input[name="company_name"]').prop('required', true);
            $('input[name="email"]').prop('required', true);
            $('input[name="address"]').prop('required', true);
            $('input[name="city"]').prop('required', true);
        } else {
            $('.asterisk').hide();
            $('input[name="company_name"]').prop('required', false);
            $('input[name="email"]').prop('required', false);
            $('input[name="address"]').prop('required', false);
            $('input[name="city"]').prop('required', false);
        }
    });

    $('#user-toggle').on('change', function() {
        if ($(this).is(':checked')) {
            $('.user-input').show(200);
            $('input[name="name"]').prop('required', true);
            $('input[name="password"]').prop('required', true);
        } else {
            $('.user-input').hide(200);
            $('input[name="name"]').prop('required', false);
            $('input[name="password"]').prop('required', false);
        }
    });
</script>
@include('backend.partials.gstin-autofill', ['gstinField' => 'tax_no', 'nameField' => 'customer_name'])
@endpush

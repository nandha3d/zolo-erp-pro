@extends('backend.layout.main') @section('content')

<x-validation-error fieldName="name" />
<x-validation-error fieldName="code" />
<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section>
    <div class="container-fluid">
        <button class="btn btn-info" data-toggle="modal" data-target="#createModal"><i class="dripicons-plus"></i> {{__('db.Add Currency')}} </button>&nbsp;
    </div>
    <div class="table-responsive">
        <table id="currency-table" class="table">
            <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{__('db.Currency Name')}}</th>
                    <th>{{__('db.Currency Code')}}</th>
                    <th>{{__('db.symbol')}}</th>
                    <th>{{__('db.Exchange Rate')}}</th>
                    <th class="not-exported">{{__('db.action')}}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lims_currency_all as $key=>$currency_data)
                <tr data-id="{{$currency_data->id}}">
                    <td>{{$key}}</td>
                    <td>{{ $currency_data->name }}</td>
                    <td>{{ $currency_data->code }}</td>
                    <td>{{ $currency_data->symbol }}</td>
                    <td>{{ $currency_data->exchange_rate }}</td>
                    <td>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">{{__('db.action')}}
                                <span class="caret"></span>
                                <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                                <li><button type="button" data-id="{{$currency_data->id}}" data-name="{{$currency_data->name}}" data-code="{{$currency_data->code}}" data-exchange_rate="{{$currency_data->exchange_rate}}" class="edit-btn btn btn-link" data-toggle="modal" data-target="#editModal"><i class="dripicons-document-edit"></i> {{__('db.edit')}}</button></li>
                                @if($currency_data->exchange_rate != 1)
                                <li class="divider"></li>
                                {{ Form::open(['route' => ['currency.destroy', $currency_data->id], 'method' => 'DELETE'] ) }}
                                <li>
                                    <button type="submit" class="btn btn-link" onclick="return confirm('Are you sure want to delete?')"><i class="dripicons-trash"></i> {{__('db.delete')}}</button>
                                </li>
                                {{ Form::close() }}
                                @endif
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<div id="createModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('currency.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">
                        <span class="zolo-brand-mark" style="width:30px;height:30px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;box-shadow:0 2px 8px rgba(99,102,241,0.35);"><i class="dripicons-wallet" style="font-size:14px;"></i></span>
                        {{ __('db.Add Currency') }}
                    </h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close">
                        <span aria-hidden="true"><i class="dripicons-cross"></i></span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Dynamic Currency Auto-Fill Engine -->
                    <div class="form-group zolo-preset-wrap" style="background: rgba(99, 102, 241, 0.06); border: 1.5px dashed var(--neo-border); border-radius: 12px; padding: 14px 16px; margin-bottom: 20px;">
                        <label class="font-weight-bold" style="color: var(--neo-primary);"><i class="dripicons-swap mr-1"></i> Quick Select / Auto-Fill Currency</label>
                        <select id="create_currency_preset" class="form-control selectpicker" data-live-search="true" title="Search or pick currency (e.g. Indian Rupee, USD, EUR...)">
                            <option value="" disabled selected>{{ __('Select or search a currency...') }}</option>
                            <optgroup label="Popular & Major Currencies">
                                <option value="INR" data-name="Indian Rupee" data-code="INR" data-symbol="₹" data-rate="83.50">Indian Rupee (INR - ₹)</option>
                                <option value="USD" data-name="US Dollar" data-code="USD" data-symbol="$" data-rate="1.00">US Dollar (USD - $)</option>
                                <option value="EUR" data-name="Euro" data-code="EUR" data-symbol="€" data-rate="0.92">Euro (EUR - €)</option>
                                <option value="GBP" data-name="British Pound" data-code="GBP" data-symbol="£" data-rate="0.79">British Pound (GBP - £)</option>
                                <option value="AED" data-name="UAE Dirham" data-code="AED" data-symbol="د.إ" data-rate="3.67">UAE Dirham (AED - د.إ)</option>
                                <option value="SAR" data-name="Saudi Riyal" data-code="SAR" data-symbol="﷼" data-rate="3.75">Saudi Riyal (SAR - ﷼)</option>
                                <option value="CAD" data-name="Canadian Dollar" data-code="CAD" data-symbol="C$" data-rate="1.36">Canadian Dollar (CAD - C$)</option>
                                <option value="AUD" data-name="Australian Dollar" data-code="AUD" data-symbol="A$" data-rate="1.52">Australian Dollar (AUD - A$)</option>
                                <option value="SGD" data-name="Singapore Dollar" data-code="SGD" data-symbol="S$" data-rate="1.35">Singapore Dollar (SGD - S$)</option>
                            </optgroup>
                            <optgroup label="Asia & Middle East">
                                <option value="BDT" data-name="Bangladeshi Taka" data-code="BDT" data-symbol="৳" data-rate="117.00">Bangladeshi Taka (BDT - ৳)</option>
                                <option value="PKR" data-name="Pakistani Rupee" data-code="PKR" data-symbol="₨" data-rate="278.50">Pakistani Rupee (PKR - ₨)</option>
                                <option value="LKR" data-name="Sri Lankan Rupee" data-code="LKR" data-symbol="Rs" data-rate="302.00">Sri Lankan Rupee (LKR - Rs)</option>
                                <option value="NPR" data-name="Nepalese Rupee" data-code="NPR" data-symbol="₨" data-rate="133.50">Nepalese Rupee (NPR - ₨)</option>
                                <option value="MYR" data-name="Malaysian Ringgit" data-code="MYR" data-symbol="RM" data-rate="4.71">Malaysian Ringgit (MYR - RM)</option>
                                <option value="JPY" data-name="Japanese Yen" data-code="JPY" data-symbol="¥" data-rate="156.00">Japanese Yen (JPY - ¥)</option>
                                <option value="CNY" data-name="Chinese Yuan" data-code="CNY" data-symbol="¥" data-rate="7.24">Chinese Yuan (CNY - ¥)</option>
                                <option value="QAR" data-name="Qatari Rial" data-code="QAR" data-symbol="﷼" data-rate="3.64">Qatari Rial (QAR - ﷼)</option>
                                <option value="KWD" data-name="Kuwaiti Dinar" data-code="KWD" data-symbol="د.ك" data-rate="0.31">Kuwaiti Dinar (KWD - د.ك)</option>
                                <option value="OMR" data-name="Omani Rial" data-code="OMR" data-symbol="﷼" data-rate="0.38">Omani Rial (OMR - ﷼)</option>
                                <option value="BHD" data-name="Bahraini Dinar" data-code="BHD" data-symbol=".د.ب" data-rate="0.38">Bahraini Dinar (BHD - .د.ب)</option>
                                <option value="THB" data-name="Thai Baht" data-code="THB" data-symbol="฿" data-rate="36.80">Thai Baht (THB - ฿)</option>
                                <option value="IDR" data-name="Indonesian Rupiah" data-code="IDR" data-symbol="Rp" data-rate="16250.00">Indonesian Rupiah (IDR - Rp)</option>
                                <option value="PHP" data-name="Philippine Peso" data-code="PHP" data-symbol="₱" data-rate="58.60">Philippine Peso (PHP - ₱)</option>
                                <option value="VND" data-name="Vietnamese Dong" data-code="VND" data-symbol="₫" data-rate="25450.00">Vietnamese Dong (VND - ₫)</option>
                            </optgroup>
                            <optgroup label="Europe & Americas & Africa">
                                <option value="CHF" data-name="Swiss Franc" data-code="CHF" data-symbol="CHF" data-rate="0.91">Swiss Franc (CHF - CHF)</option>
                                <option value="SEK" data-name="Swedish Krona" data-code="SEK" data-symbol="kr" data-rate="10.50">Swedish Krona (SEK - kr)</option>
                                <option value="NOK" data-name="Norwegian Krone" data-code="NOK" data-symbol="kr" data-rate="10.60">Norwegian Krone (NOK - kr)</option>
                                <option value="TRY" data-name="Turkish Lira" data-code="TRY" data-symbol="₺" data-rate="32.50">Turkish Lira (TRY - ₺)</option>
                                <option value="RUB" data-name="Russian Ruble" data-code="RUB" data-symbol="₽" data-rate="90.50">Russian Ruble (RUB - ₽)</option>
                                <option value="BRL" data-name="Brazilian Real" data-code="BRL" data-symbol="R$" data-rate="5.35">Brazilian Real (BRL - R$)</option>
                                <option value="MXN" data-name="Mexican Peso" data-code="MXN" data-symbol="Mex$" data-rate="18.10">Mexican Peso (MXN - Mex$)</option>
                                <option value="ZAR" data-name="South African Rand" data-code="ZAR" data-symbol="R" data-rate="18.30">South African Rand (ZAR - R)</option>
                                <option value="EGP" data-name="Egyptian Pound" data-code="EGP" data-symbol="E£" data-rate="47.60">Egyptian Pound (EGP - E£)</option>
                                <option value="NGN" data-name="Nigerian Naira" data-code="NGN" data-symbol="₦" data-rate="1480.00">Nigerian Naira (NGN - ₦)</option>
                                <option value="KES" data-name="Kenyan Shilling" data-code="KES" data-symbol="KSh" data-rate="130.00">Kenyan Shilling (KES - KSh)</option>
                            </optgroup>
                        </select>
                        <small class="form-text text-muted mt-1"><i class="dripicons-information mr-1"></i> Selecting a preset instantly populates Name, Code, Symbol, and Rate.</small>
                    </div>

                    <p class="italic"><small>{{ __('db.The field labels marked with * are required input fields') }}.</small></p>

                    <div class="form-group">
                        <label>{{ __('db.name') }} *</label>
                        <input type="text" name="name" required class="form-control" placeholder="{{ __('db.Type currency name') }}">
                    </div>

                    <div class="form-group">
                        <label>{{ __('db.Code') }} * <x-info title="USD, NGN, INR, PKR ..." type="info" /></label>
                        <input type="text" name="code" required class="form-control" placeholder="{{ __('db.Type currency code') }}">
                    </div>

                    <div class="form-group">
                        <label>{{ __('db.symbol') }} <x-info title="$, ₹, ₦, € ..." type="info" /></label>
                        <input type="text" name="symbol" class="form-control" placeholder="{{ __('db.symbol') }}">
                    </div>

                    <div class="form-group">
                        <label>
                            {{ __('db.Exchange Rate') }} * <x-info title="{{ __('db.If this is your default currency, the exchange rate must be 1') }}" type="info" />
                        </label>
                        <input type="number" name="exchange_rate" required class="form-control" id="add_exchange_rate" min="0.0000001" step="any" placeholder="{{ __('db.Type exchange rate') }}">
                    </div>

                    <div class="form-group mb-0 mt-3 text-right">
                        <button type="button" data-dismiss="modal" class="btn btn-secondary mr-2">{{ __('db.Cancel') }}</button>
                        <input type="submit" value="{{ __('db.submit') }}" class="btn btn-primary">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


<div id="editModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
    <div role="document" class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('currency.update', 1) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 id="exampleModalLabel" class="modal-title">
                        <span class="zolo-brand-mark" style="width:30px;height:30px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;box-shadow:0 2px 8px rgba(99,102,241,0.35);"><i class="dripicons-document-edit" style="font-size:14px;"></i></span>
                        {{ __('db.Update Currency') }}
                    </h5>
                    <button type="button" data-dismiss="modal" aria-label="Close" class="close">
                        <span aria-hidden="true"><i class="dripicons-cross"></i></span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Dynamic Currency Auto-Fill Engine for Edit -->
                    <div class="form-group zolo-preset-wrap" style="background: rgba(99, 102, 241, 0.06); border: 1.5px dashed var(--neo-border); border-radius: 12px; padding: 14px 16px; margin-bottom: 20px;">
                        <label class="font-weight-bold" style="color: var(--neo-primary);"><i class="dripicons-swap mr-1"></i> Quick Select / Auto-Fill Currency</label>
                        <select id="edit_currency_preset" class="form-control selectpicker" data-live-search="true" title="Search or pick currency (e.g. Indian Rupee, USD, EUR...)">
                            <option value="" disabled selected>{{ __('Select or search a currency...') }}</option>
                            <optgroup label="Popular & Major Currencies">
                                <option value="INR" data-name="Indian Rupee" data-code="INR" data-symbol="₹" data-rate="83.50">Indian Rupee (INR - ₹)</option>
                                <option value="USD" data-name="US Dollar" data-code="USD" data-symbol="$" data-rate="1.00">US Dollar (USD - $)</option>
                                <option value="EUR" data-name="Euro" data-code="EUR" data-symbol="€" data-rate="0.92">Euro (EUR - €)</option>
                                <option value="GBP" data-name="British Pound" data-code="GBP" data-symbol="£" data-rate="0.79">British Pound (GBP - £)</option>
                                <option value="AED" data-name="UAE Dirham" data-code="AED" data-symbol="د.إ" data-rate="3.67">UAE Dirham (AED - د.إ)</option>
                                <option value="SAR" data-name="Saudi Riyal" data-code="SAR" data-symbol="﷼" data-rate="3.75">Saudi Riyal (SAR - ﷼)</option>
                                <option value="CAD" data-name="Canadian Dollar" data-code="CAD" data-symbol="C$" data-rate="1.36">Canadian Dollar (CAD - C$)</option>
                                <option value="AUD" data-name="Australian Dollar" data-code="AUD" data-symbol="A$" data-rate="1.52">Australian Dollar (AUD - A$)</option>
                                <option value="SGD" data-name="Singapore Dollar" data-code="SGD" data-symbol="S$" data-rate="1.35">Singapore Dollar (SGD - S$)</option>
                            </optgroup>
                            <optgroup label="Asia & Middle East">
                                <option value="BDT" data-name="Bangladeshi Taka" data-code="BDT" data-symbol="৳" data-rate="117.00">Bangladeshi Taka (BDT - ৳)</option>
                                <option value="PKR" data-name="Pakistani Rupee" data-code="PKR" data-symbol="₨" data-rate="278.50">Pakistani Rupee (PKR - ₨)</option>
                                <option value="LKR" data-name="Sri Lankan Rupee" data-code="LKR" data-symbol="Rs" data-rate="302.00">Sri Lankan Rupee (LKR - Rs)</option>
                                <option value="NPR" data-name="Nepalese Rupee" data-code="NPR" data-symbol="₨" data-rate="133.50">Nepalese Rupee (NPR - ₨)</option>
                                <option value="MYR" data-name="Malaysian Ringgit" data-code="MYR" data-symbol="RM" data-rate="4.71">Malaysian Ringgit (MYR - RM)</option>
                                <option value="JPY" data-name="Japanese Yen" data-code="JPY" data-symbol="¥" data-rate="156.00">Japanese Yen (JPY - ¥)</option>
                                <option value="CNY" data-name="Chinese Yuan" data-code="CNY" data-symbol="¥" data-rate="7.24">Chinese Yuan (CNY - ¥)</option>
                                <option value="QAR" data-name="Qatari Rial" data-code="QAR" data-symbol="﷼" data-rate="3.64">Qatari Rial (QAR - ﷼)</option>
                                <option value="KWD" data-name="Kuwaiti Dinar" data-code="KWD" data-symbol="د.ك" data-rate="0.31">Kuwaiti Dinar (KWD - د.ك)</option>
                                <option value="OMR" data-name="Omani Rial" data-code="OMR" data-symbol="﷼" data-rate="0.38">Omani Rial (OMR - ﷼)</option>
                                <option value="BHD" data-name="Bahraini Dinar" data-code="BHD" data-symbol=".د.b" data-rate="0.38">Bahraini Dinar (BHD - .د.ب)</option>
                                <option value="THB" data-name="Thai Baht" data-code="THB" data-symbol="฿" data-rate="36.80">Thai Baht (THB - ฿)</option>
                                <option value="IDR" data-name="Indonesian Rupiah" data-code="IDR" data-symbol="Rp" data-rate="16250.00">Indonesian Rupiah (IDR - Rp)</option>
                                <option value="PHP" data-name="Philippine Peso" data-code="PHP" data-symbol="₱" data-rate="58.60">Philippine Peso (PHP - ₱)</option>
                                <option value="VND" data-name="Vietnamese Dong" data-code="VND" data-symbol="₫" data-rate="25450.00">Vietnamese Dong (VND - ₫)</option>
                            </optgroup>
                            <optgroup label="Europe & Americas & Africa">
                                <option value="CHF" data-name="Swiss Franc" data-code="CHF" data-symbol="CHF" data-rate="0.91">Swiss Franc (CHF - CHF)</option>
                                <option value="SEK" data-name="Swedish Krona" data-code="SEK" data-symbol="kr" data-rate="10.50">Swedish Krona (SEK - kr)</option>
                                <option value="NOK" data-name="Norwegian Krone" data-code="NOK" data-symbol="kr" data-rate="10.60">Norwegian Krone (NOK - kr)</option>
                                <option value="TRY" data-name="Turkish Lira" data-code="TRY" data-symbol="₺" data-rate="32.50">Turkish Lira (TRY - ₺)</option>
                                <option value="RUB" data-name="Russian Ruble" data-code="RUB" data-symbol="₽" data-rate="90.50">Russian Ruble (RUB - ₽)</option>
                                <option value="BRL" data-name="Brazilian Real" data-code="BRL" data-symbol="R$" data-rate="5.35">Brazilian Real (BRL - R$)</option>
                                <option value="MXN" data-name="Mexican Peso" data-code="MXN" data-symbol="Mex$" data-rate="18.10">Mexican Peso (MXN - Mex$)</option>
                                <option value="ZAR" data-name="South African Rand" data-code="ZAR" data-symbol="R" data-rate="18.30">South African Rand (ZAR - R)</option>
                                <option value="EGP" data-name="Egyptian Pound" data-code="EGP" data-symbol="E£" data-rate="47.60">Egyptian Pound (EGP - E£)</option>
                                <option value="NGN" data-name="Nigerian Naira" data-code="NGN" data-symbol="₦" data-rate="1480.00">Nigerian Naira (NGN - ₦)</option>
                                <option value="KES" data-name="Kenyan Shilling" data-code="KES" data-symbol="KSh" data-rate="130.00">Kenyan Shilling (KES - KSh)</option>
                            </optgroup>
                        </select>
                    </div>

                    <p class="italic"><small>{{ __('db.The field labels marked with * are required input fields') }}.</small></p>

                    <div class="form-group">
                        <label>{{ __('db.name') }} *</label>
                        <input type="text" name="name" required class="form-control" placeholder="{{ __('db.Type currency name') }}">
                    </div>

                    <div class="form-group">
                        <label>{{ __('db.Code') }} *</label>
                        <input type="text" name="code" required class="form-control" placeholder="{{ __('db.Type currency code') }}">
                    </div>

                    <div class="form-group">
                        <label>{{ __('db.symbol') }} <x-info title="$, ₹, ₦, € ..." type="info" /></label>
                        <input type="text" name="symbol" class="form-control" placeholder="{{ __('db.symbol') }}">
                    </div>

                    <div class="form-group">
                        <label>
                            {{ __('db.Exchange Rate') }} * 
                            <i class="dripicons-question" data-toggle="tooltip" title="{{ __('db.If this is your default currency, the exchange rate must be 1') }}"></i>
                        </label>
                        <input type="number" name="exchange_rate" required class="form-control" id="edit_exchange_rate" min="0.0000001" step="any" placeholder="{{ __('db.Type exchange rate') }}">
                    </div>

                    <input type="hidden" name="currency_id">

                    <div class="form-group mb-0 mt-3 text-right">
                        <button type="button" data-dismiss="modal" class="btn btn-secondary mr-2">{{ __('db.Cancel') }}</button>
                        <input type="submit" value="{{ __('db.submit') }}" class="btn btn-primary">
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>


@endsection

@push('scripts')
<script type="text/javascript">

    $('#add_exchange_rate,#edit_exchange_rate').on('input',function(){
        var exchange_rate = $(this).val();
        var default_exchange_rate = {{$currency->exchange_rate}};
        if(exchange_rate == default_exchange_rate){
            var message = "{{__('db.Only default currency can have 1 as exchange rate. Please change the exchange rate of your default currency')}}";
            $(this).parent().append('<div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><span>'+message+' - {{$currency->name}}</span></div>');
            $(this).closest('form').find(':input[type="submit"]').prop('disabled', true);
        }else{
            $(this).closest('form').find('.alert').remove();
            $(this).closest('form').find(':input[type="submit"]').prop('disabled', false);
        }
    });

    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #currency-menu").addClass("active");

    $(document).ready(function() {
        // Dynamic Currency Auto-Fill Engine for Add Modal
        $('#create_currency_preset').on('changed.bs.select change', function() {
            var $opt = $(this).find('option:selected');
            var name = $opt.data('name');
            var code = $opt.data('code');
            var symbol = $opt.data('symbol');
            var rate = $opt.data('rate');
            
            if (name) {
                $('#createModal input[name="name"]').val(name).trigger('change');
                $('#createModal input[name="code"]').val(code).trigger('change');
                $('#createModal input[name="symbol"]').val(symbol).trigger('change');
                $('#createModal input[name="exchange_rate"]').val(rate).trigger('input');
            }
        });

        // Dynamic Currency Auto-Fill Engine for Edit Modal
        $('#edit_currency_preset').on('changed.bs.select change', function() {
            var $opt = $(this).find('option:selected');
            var name = $opt.data('name');
            var code = $opt.data('code');
            var symbol = $opt.data('symbol');
            var rate = $opt.data('rate');
            
            if (name) {
                $('#editModal input[name="name"]').val(name).trigger('change');
                $('#editModal input[name="code"]').val(code).trigger('change');
                $('#editModal input[name="symbol"]').val(symbol).trigger('change');
                $('#editModal input[name="exchange_rate"]').val(rate).trigger('input');
            }
        });

        $(document).on('click', '.edit-btn', function() {
            $("#editModal input[name='currency_id']").val($(this).data('id'));
            $("#editModal input[name='name']").val($(this).data('name'));
            $("#editModal input[name='code']").val($(this).data('code'));
            $("#editModal input[name='symbol']").val($(this).data('symbol'));
            $("#editModal input[name='exchange_rate']").val($(this).data('exchange_rate'));
            
            // Sync preset dropdown if matching code exists
            var curCode = $(this).data('code');
            if (curCode) {
                $('#edit_currency_preset').val(curCode).selectpicker('refresh');
            }
        });
    });

    $('#currency-table').DataTable( {
        "order": [],
        'language': {
            'lengthMenu': '_MENU_ {{__("db.records per page")}}',
             "info":      '<small>{{__("db.Showing")}} _START_ - _END_ (_TOTAL_)</small>',
            "search":  '{{__("db.Search")}}',
            'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
            }
        },
        'columnDefs': [
            {
                "orderable": false,
                'targets': [0, 4]
            },
            {
                'render': function(data, type, row, meta){
                    if(type === 'display'){
                        data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                    }

                   return data;
                },
                'checkboxes': {
                   'selectRow': true,
                   'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                },
                'targets': [0]
            }
        ],
        'select': { style: 'multi',  selector: 'td:first-child'},
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        dom: '<"row"lfB>rtip',
        buttons: [
            {
                extend: 'pdf',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                    stripHtml: false
                },
                customize: function(doc) {
                    for (var i = 1; i < doc.content[1].table.body.length; i++) {
                        if (doc.content[1].table.body[i][0].text.indexOf('<img src=') !== -1) {
                            var imagehtml = doc.content[1].table.body[i][0].text;
                            var regex = /<img.*?src=['"](.*?)['"]/;
                            var src = regex.exec(imagehtml)[1];
                            var tempImage = new Image();
                            tempImage.src = src;
                            var canvas = document.createElement("canvas");
                            canvas.width = tempImage.width;
                            canvas.height = tempImage.height;
                            var ctx = canvas.getContext("2d");
                            ctx.drawImage(tempImage, 0, 0);
                            var imagedata = canvas.toDataURL("image/png");
                            delete doc.content[1].table.body[i][0].text;
                            doc.content[1].table.body[i][0].image = imagedata;
                            doc.content[1].table.body[i][0].fit = [30, 30];
                        }
                    }
                },
            },
            {
                extend: 'excel',
                text: '<i title="export to excel" class="dripicons-document-new"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                    format: {
                        body: function ( data, row, column, node ) {
                            if (column === 0 && (data.indexOf('<img src=') !== -1)) {
                                var regex = /<img.*?src=['"](.*?)['"]/;
                                data = regex.exec(data)[1];
                            }
                            return data;
                        }
                    }
                },
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                    format: {
                        body: function ( data, row, column, node ) {
                            if (column === 0 && (data.indexOf('<img src=') !== -1)) {
                                var regex = /<img.*?src=['"](.*?)['"]/;
                                data = regex.exec(data)[1];
                            }
                            return data;
                        }
                    }
                },
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:Not(.not-exported)',
                    rows: ':visible',
                    stripHtml: false
                },
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
    } );

</script>
@endpush

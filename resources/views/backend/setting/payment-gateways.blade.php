@extends('backend.layout.main') @section('content')

<x-success-message key="message" />
<x-error-message key="not_permitted" />

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/pages/setting-payment-gateways.css') }}">
@endpush
<x-settings-command-bar active="payment_gateway" />

<section class="settings-workspace-view forms">
    <div class="settings-form-card card">
        <div class="card-header d-flex align-items-center">
            <h4>{{__('db.Payment Gateways')}}</h4>
        </div>
                    <div class="card-body">
                        <p class="italic"><small>{{__('db.The field labels marked with * are required input fields')}}.</small></p>
                        {!! Form::open(['route' => 'setting.gateway.update', 'files' => true, 'method' => 'post', 'data-shell-steps' => '']) !!}
                        <div class="row">
                            @foreach($payment_gateways as $pg)
                            <div class="col-md-12 mt-1 mb-2 pg-step" data-step-title="{{ $pg->name }}">
                                <h4 class="d-flex align-items-center justify-content-between">
                                    {{$pg->name}} {{__('db.Details')}}

                                    <div class="u-w-200px">
                                        <select class="form-control" name="module_status[{{ $loop->index }}][]" multiple>
                                            @php
                                                $modules = json_decode($pg->module_status, true);
                                            @endphp

                                            @foreach(['pos', 'ecommerce'] as $module)
                                                <option value="{{ $module }}" {{ !empty($modules[$module]) && $modules[$module] ? 'selected' : '' }}>
                                                    {{ ucfirst($module) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </h4>
                                <hr>
                                <input type="hidden" name="pg_name[]" class="form-control" value="{{$pg->name}}" />
                                @php
                                $lines = explode(';',$pg->details);
                                $keys = explode(',', $lines[0]);
                                $vals = explode(',', $lines[1]);

                                $results = array_combine($keys, $vals);
                                @endphp
                                @foreach ($results as $key => $value)
                                <div class="form-group">
                                    <label>{{$key}}</label>
                                    @if($key == 'Mode')
                                        <select name="{{$pg->name.'_'.str_replace(' ','_',$key)}}" class="selectpicker form-control">
                                            <option @if($value == 'sandbox') selected @endif value="sandbox">Sandbox</option>
                                            <option @if($value == 'live') selected @endif value="live">Live</option>
                                        </select>
                                    @else
                                        <input type="text" name="{{$pg->name.'_'.str_replace(' ','_',$key)}}" class="form-control" value="{{$value}}" />
                                    @endif

                                </div>
                                @endforeach
                            </div>
                            @endforeach

                        </div>
                        <div class="form-group">
                            <input type="submit" value="{{__('db.submit')}}" class="btn btn-primary">
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
</section>

@endsection

@push('scripts')
<script>
    if ($('.activate').is(':checked')) {
        $(this).siblings('input[type="text"]').val(1);
    } else {
        $(this).siblings('input[type="text"]').val(0);
    }
    $(document).on('click', '.activate', function(){
        if ($(this).is(':checked')) {
            $(this).siblings('input[type="hidden"]').val(1);
        } else if (!$(this).is(':checked')) {
            $(this).siblings('input[type="hidden"]').val(0);
        }
    })
</script>
@endpush

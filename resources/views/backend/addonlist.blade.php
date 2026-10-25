@php
    $layout = 'backend.layout.main';
    if (config('database.connections.zoloerp_landlord')) {
        $layout = 'landlord.layout.main';
    }
@endphp

@extends($layout)
@section('content')

@push('css')
<link rel="stylesheet" href="{{ versioned_asset('css/pages/addonlist.css') }}">
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="forms">
    <div class="container-fluid">
        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-2 addon-page-head">
            <div>
                <h3 class="font-weight-bold mb-1 u-c-neo-text-primary">
                    <i class="dripicons-view-thumb mr-2 text-primary"></i>zoloERP SaaS &amp; Industry Addon Marketplace
                </h3>
                <p class="text-muted mb-0 small">
                    Modular plug-and-play industry solutions connected into centralized multi-warehouse stock and double-entry accounting
                </p>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge badge-primary px-3 py-2 rounded-pill font-weight-bold mr-2 u-fs-0_85rem">
                    <i class="dripicons-cloud mr-1"></i> SaaS Multi-Tenant Ready
                </span>
                <span class="badge badge-success px-3 py-2 rounded-pill font-weight-bold u-fs-0_85rem">
                    <i class="dripicons-checkmark mr-1"></i> Centralized ERP Linked
                </span>
            </div>
        </div>

        {{-- Addon Cards Grid --}}
        <div class="row addon-grid">
            @foreach($addons as $addon)
                @php
                    $isActive = in_array($addon['key'], $activeModules);
                @endphp
                <div class="col-lg-6 col-xl-4 mb-3">
                    <div class="card addon-card bg-white h-100 shadow-sm p-3 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-start justify-content-between mb-2">
                                <div class="d-flex align-items-center">
                                    <div class="addon-icon-box mr-3 z-tone-{{ $addon['tone'] }}">
                                        <i class="{{ $addon['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <span class="badge badge-light border text-muted px-2 py-0 u-fs-0_72rem">{{ $addon['category'] }}</span>
                                        <h5 class="font-weight-bold mb-0 mt-1 text-dark u-fs-1_05rem">{{ $addon['name'] }}</h5>
                                    </div>
                                </div>
                                <label class="switch-custom mb-0">
                                    <input type="checkbox" class="toggle-addon-cb" data-key="{{ $addon['key'] }}" {{ $isActive ? 'checked' : '' }}>
                                    <span class="slider-custom"></span>
                                </label>
                            </div>

                            <p class="text-muted small mb-2 u-lh-1_4">
                                {{ $addon['description'] }}
                            </p>
                        </div>

                        <div>
                            <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                                <div class="d-flex align-items-center">
                                    <span class="badge {{ $isActive ? 'badge-success' : 'badge-secondary' }} px-2 py-1 mr-2 status-badge-{{ $addon['key'] }}">
                                        {{ $isActive ? 'Enabled' : 'Disabled' }}
                                    </span>
                                    <small class="text-muted"><i class="dripicons-lock mr-1"></i>ERP Synced</small>
                                </div>
                                <div>
                                    @if(Route::has($addon['route']))
                                        <a href="{{ route($addon['route']) }}" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            Open Module &rarr;
                                        </a>
                                    @else
                                        <span class="btn btn-light btn-sm text-muted rounded-pill px-3">Installed</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script type="text/javascript">
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(document).on('change', '.toggle-addon-cb', function() {
        var key = $(this).data('key');
        var isChecked = $(this).is(':checked');
        var badge = $('.status-badge-' + key);

        $.post("{{ route('addon.toggle') }}", {
            key: key
        }, function(res) {
            if (res.status) {
                badge.removeClass('badge-secondary').addClass('badge-success').text('Enabled');
            } else {
                badge.removeClass('badge-success').addClass('badge-secondary').text('Disabled');
            }
        });
    });
</script>
@endpush

@php
    $layout = 'backend.layout.main';
    if (config('database.connections.zoloerp_landlord')) {
        $layout = 'landlord.layout.main';
    }
@endphp

@extends($layout)
@section('content')

@push('css')
<style>
/* Four cards per row on wide screens so every addon is visible on one screen. */
@media (min-width: 1200px) {
    .addon-page-head h3 { font-size: 18px; margin: 0 !important; }
    .addon-page-head p { font-size: 12px; }
    .addon-grid > [class*="col-"] { flex: 0 0 100%; max-width: 100%; margin-bottom: 4px !important; }
    .addon-grid .addon-card { display: grid !important; grid-template-columns: clamp(330px, 34%, 450px) minmax(0, 1fr) auto; grid-template-areas: "head desc foot"; align-items: center; column-gap: 16px; padding: 2px 14px !important; min-height: 0 !important; }
    .addon-grid .addon-card .badge-light { font-size: 10px !important; }
    .addon-grid .addon-card .switch-custom { flex: none; margin-left: 10px !important; }
    .addon-grid .addon-card .addon-icon-box + div { display: flex; flex-direction: row-reverse; justify-content: flex-end; align-items: center; gap: 8px; }
    .addon-grid .addon-card .addon-icon-box + div > h5 { margin: 0 !important; white-space: nowrap; }
    .addon-grid .addon-card .addon-icon-box { width: 30px !important; height: 30px !important; font-size: 14px !important; }
    .addon-grid .addon-card > div:first-child { display: contents; }
    .addon-grid .addon-card > div:first-child > div { grid-area: head; margin-bottom: 0 !important; }
    .addon-grid .addon-card > div:first-child > p { grid-area: desc; margin: 0 !important; align-self: center; font-size: 11.5px; line-height: 1.3 !important; }
    .addon-grid .addon-card > div:last-child { grid-area: foot; }
    .addon-grid .addon-card > div:last-child > div { padding-top: 0 !important; border-top: 0 !important; flex-direction: row; align-items: center !important; gap: 10px; }
    .addon-grid .addon-card h5 { font-size: 14px !important; }
    .addon-grid .addon-card .addon-icon-box { width: 36px; height: 36px; font-size: 16px; margin-right: 10px !important; }
    .addon-grid .addon-card small { white-space: nowrap; font-size: 11px; }
    .addon-grid .addon-card .btn { height: 26px !important; min-height: 0 !important; padding: 0 10px !important; line-height: 24px !important; font-size: 12px !important; }
}
.addon-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.addon-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
}
.addon-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
}
.switch-custom {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
}
.switch-custom input {
    opacity: 0;
    width: 0;
    height: 0;
}
.slider-custom {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #cbd5e1;
    transition: .3s;
    border-radius: 24px;
}
.slider-custom:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
input:checked + .slider-custom {
    background-color: #10b981;
}
input:checked + .slider-custom:before {
    transform: translateX(20px);
}
</style>
@endpush

<x-success-message key="message" />
<x-error-message key="not_permitted" />

<section class="forms">
    <div class="container-fluid">
        {{-- Header --}}
        <div class="d-flex align-items-center justify-content-between mb-2 addon-page-head">
            <div>
                <h3 class="font-weight-bold mb-1" style="color: var(--neo-text-primary, #1e293b);">
                    <i class="dripicons-view-thumb mr-2 text-primary"></i>zoloERP SaaS &amp; Industry Addon Marketplace
                </h3>
                <p class="text-muted mb-0 small">
                    Modular plug-and-play industry solutions connected into centralized multi-warehouse stock and double-entry accounting
                </p>
            </div>
            <div class="d-flex align-items-center">
                <span class="badge badge-primary px-3 py-2 rounded-pill font-weight-bold mr-2" style="font-size: 0.85rem;">
                    <i class="dripicons-cloud mr-1"></i> SaaS Multi-Tenant Ready
                </span>
                <span class="badge badge-success px-3 py-2 rounded-pill font-weight-bold" style="font-size: 0.85rem;">
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
                                    <div class="addon-icon-box mr-3" style="background-color: {{ $addon['color'] }}15; color: {{ $addon['color'] }};">
                                        <i class="{{ $addon['icon'] }}"></i>
                                    </div>
                                    <div>
                                        <span class="badge badge-light border text-muted px-2 py-0" style="font-size: 0.72rem;">{{ $addon['category'] }}</span>
                                        <h5 class="font-weight-bold mb-0 mt-1 text-dark" style="font-size: 1.05rem;">{{ $addon['name'] }}</h5>
                                    </div>
                                </div>
                                <label class="switch-custom mb-0">
                                    <input type="checkbox" class="toggle-addon-cb" data-key="{{ $addon['key'] }}" {{ $isActive ? 'checked' : '' }}>
                                    <span class="slider-custom"></span>
                                </label>
                            </div>

                            <p class="text-muted small mb-2" style="line-height: 1.4;">
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

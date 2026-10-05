@extends('backend.layout.main')

@push('css')
<link rel="stylesheet" href="{{ asset('css/operations-workspace.css') }}">
@endpush

@section('content')
<div id="ops-loading-bar"></div>

@php
    $profileData = $profile ?? app(\App\Services\Industry\IndustryProfileService::class)->settings($context);
    $navigation = app(\App\Services\Platform\CapabilityService::class)->forNavigation($context);
    $activeArea = $area ?? (request()->is('operations/manufacturing*') ? 'manufacturing' : (request()->is('operations/projects*') ? 'projects' : (request()->is('operations/stock*') ? 'stock' : (request()->is('operations/profiles*') ? 'profiles' : 'job-work'))));
@endphp

<div class="container-fluid px-3 pt-2">
    <!-- Operations Single-Screen Command Bar -->
    <div class="ops-command-bar">
        <div class="ops-command-title">
            <span class="badge badge-primary px-2 py-1 text-uppercase font-weight-bold" style="font-size: 11px; border-radius: 6px; letter-spacing: 0.04em;">
                <i class="fa fa-cogs mr-1"></i> Operations
            </span>
            <span class="text-muted small d-none d-md-inline">
                <strong>{{ ($profileData['profile'] ?? 'general_trading') === 'general_trading' ? 'General Trading' : ucwords(str_replace('_', ' ', $profileData['profile'] ?? '')) }}</strong> &bull; Co. {{ $context->companyId }} &bull; Br. {{ $context->branchId }} &bull; FY {{ $context->financialYearId }}
            </span>
        </div>

        <ul class="ops-nav-pills">
            @if(in_array('operations.job_work', $navigation, true))
            <li>
                <a class="nav-link {{ $activeArea === 'job-work' ? 'active' : '' }}" href="{{ url('/operations/job-work') }}">
                    <i class="dripicons-network-3 mr-1"></i> Job Work
                </a>
            </li>
            @endif
            @if(in_array('manufacturing.bom', $navigation, true))
            <li>
                <a class="nav-link {{ $activeArea === 'manufacturing' ? 'active' : '' }}" href="{{ url('/operations/manufacturing') }}">
                    <i class="fa fa-industry mr-1"></i> BOM &amp; Production
                </a>
            </li>
            @endif
            @if(in_array('operations.projects', $navigation, true))
            <li>
                <a class="nav-link {{ $activeArea === 'projects' ? 'active' : '' }}" href="{{ url('/operations/projects') }}">
                    <i class="dripicons-briefcase mr-1"></i> Sites &amp; Projects
                </a>
            </li>
            @endif
            <li>
                <a class="nav-link {{ $activeArea === 'stock' ? 'active' : '' }}" href="{{ url('/operations/stock') }}">
                    <i class="dripicons-box mr-1"></i> Stock Tools
                </a>
            </li>
            <li>
                <a class="nav-link {{ $activeArea === 'profiles' ? 'active' : '' }}" href="{{ url('/operations/profiles') }}">
                    <i class="dripicons-gear mr-1"></i> Business Profile
                </a>
            </li>
        </ul>
    </div>

    @if(session('message'))
        <div class="alert alert-success alert-dismissible fade show py-2 px-3 mb-2" role="alert" style="font-size: 13px;">
            <i class="fa fa-check-circle mr-1"></i> {{ session('message') }}
            <button type="button" class="close p-2" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-2 px-3 mb-2" role="alert" style="font-size: 13px;">
            <strong><i class="fa fa-exclamation-triangle mr-1"></i> Save failed. Please review:</strong>
            <ul class="mb-0 mt-1 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close p-2" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Dynamic Workspace Container (Target for Instant Single-Screen SPA Swaps) -->
    <div id="operations-workspace" class="ops-workspace-view">
        @yield('operations_content')
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/operations.js') }}"></script>
<script>
    // Instant SPA Tab Switching without full page reload & whiteout
    $(document).on('click', '.ops-nav-pills a', function(e) {
        var targetUrl = $(this).attr('href');
        if (!targetUrl || targetUrl.startsWith('#') || targetUrl === window.location.href) return;
        
        e.preventDefault();
        $('.ops-nav-pills a').removeClass('active');
        $(this).addClass('active');

        var bar = $('#ops-loading-bar');
        bar.removeClass('done').addClass('active');

        fetch(targetUrl, { 
            headers: { 'X-Requested-With': 'XMLHttpRequest' } 
        })
        .then(function(res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.text();
        })
        .then(function(html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newWorkspace = doc.getElementById('operations-workspace');
            if (newWorkspace) {
                $('#operations-workspace').html(newWorkspace.innerHTML);
                window.history.pushState(null, '', targetUrl);
                document.title = doc.title;
                if (window.initOperations) {
                    window.initOperations();
                }
            } else {
                window.location.href = targetUrl;
            }
        })
        .catch(function() {
            window.location.href = targetUrl;
        })
        .finally(function() {
            bar.addClass('done').removeClass('active');
            setTimeout(function() { bar.removeClass('done'); }, 300);
        });
    });

    window.addEventListener('popstate', function() {
        window.location.reload();
    });
</script>
@endpush

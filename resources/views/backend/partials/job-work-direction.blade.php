{{-- Job work runs both ways under one sidebar entry; this switch picks the direction. $direction: 'out' | 'in', or null on a single order (sidebar mark only). --}}
@if($direction && config('operations.enabled') && config('commercial.enabled'))
<nav class="jw-direction" aria-label="Job work direction">
    <a href="{{ url('/operations/job-work') }}" class="{{ $direction === 'out' ? 'active' : '' }}" @if($direction === 'out') aria-current="page" @endif>
        <i class="dripicons-export"></i> Our material to job worker <small>Material DC · Job Work GRN</small>
    </a>
    <a href="{{ url('/trade/customer-jobs') }}" class="{{ $direction === 'in' ? 'active' : '' }}" @if($direction === 'in') aria-current="page" @endif>
        <i class="dripicons-import"></i> Customer's material to us <small>Receive · return · bill charge</small>
    </a>
</nav>
<link rel="stylesheet" href="{{ versioned_asset('css/pages/partials-job-work-direction.css') }}">
@endif
@push('scripts')
<script>
    $("ul#product").addClass("show").siblings('a').attr('aria-expanded', 'true');
    $("ul#product #job-work-menu").addClass("active");
</script>
@endpush

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
<style>
.jw-direction { display: inline-flex; gap: 2px; padding: 2px; margin: 0 0 6px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; }
.jw-direction a { display: inline-flex; align-items: center; gap: 6px; height: 28px; padding: 0 12px; border-radius: 4px; font-size: 12.5px; font-weight: 600; color: #475569; text-decoration: none; white-space: nowrap; }
.jw-direction a small { font-size: 11px; font-weight: 500; color: #94a3b8; }
.jw-direction a:hover { background: #e2e8f0; color: #0f172a; }
.jw-direction a.active { background: #7c3aed; color: #fff; box-shadow: 0 1px 3px rgba(124, 58, 237, .35); }
.jw-direction a.active small { color: #ddd6fe; }
body.dark-mode .jw-direction { background: #1e293b; border-color: #334155; }
body.dark-mode .jw-direction a { color: #cbd5e1; }
body.dark-mode .jw-direction a:hover { background: #334155; color: #fff; }
</style>
@endif
@push('scripts')
<script>
    $("ul#product").addClass("show").siblings('a').attr('aria-expanded', 'true');
    $("ul#product #job-work-menu").addClass("active");
</script>
@endpush

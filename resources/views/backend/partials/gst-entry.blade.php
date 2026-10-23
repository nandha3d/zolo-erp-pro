{{-- GST mode for the Sales/Purchase entry page; renders nothing for companies without an active GST registration. --}}
@php
    $gstEntry = app(\App\Services\Tax\GstSetupService::class)->entryConfig();
@endphp
@if($gstEntry)
@php
    $gstEntry += ['kind' => $kind, 'preview_url' => url('/commercial/'.$kind.'/preview'),
        'csrf' => csrf_token(), 'decimals' => $general_setting->decimal ?? 2];
@endphp
<script>
    window.ZoloGstEntry = @json($gstEntry);
</script>
<script src="{{ asset('js/gst-entry.js') }}?v={{ filemtime(public_path('js/gst-entry.js')) }}"></script>
@endif

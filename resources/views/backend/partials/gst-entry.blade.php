{{-- GST mode for the Sales/Purchase entry page; renders nothing for companies without an active GST registration. --}}
@php $gstEntry = app(\App\Services\Tax\GstSetupService::class)->entryConfig(); @endphp
@if($gstEntry)
<script>
    window.ZoloGstEntry = @json($gstEntry + ['kind' => $kind, 'preview_url' => url('/commercial/'.$kind.'/preview'),
        'csrf' => csrf_token(), 'decimals' => $general_setting->decimal ?? 2]);
</script>
<script src="{{ asset('js/gst-entry.js') }}?v={{ filemtime(public_path('js/gst-entry.js')) }}"></script>
@endif

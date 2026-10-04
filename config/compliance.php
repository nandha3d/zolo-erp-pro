<?php

return [
    // Enable only after the Phase 5/6 dependencies and this package are reviewed together.
    'enabled' => env('ERP_COMPLIANCE_ENABLED', false),
    'gst_lookup_url' => env('GST_LOOKUP_URL'),
    'gst_lookup_token' => env('GST_LOOKUP_TOKEN'),
    'gst_lookup_timeout' => 5,
    // Review exports are deliberately separate from a statutory filing adapter.
    'export_version' => 'zolo-gst-review-v1',
];

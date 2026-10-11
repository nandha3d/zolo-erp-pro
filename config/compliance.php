<?php

return [
    // Enable only after the Phase 5/6 dependencies and this package are reviewed together.
    'enabled' => env('ERP_COMPLIANCE_ENABLED', false),
    'gst_lookup_url' => env('GST_LOOKUP_URL'),
    'gst_lookup_token' => env('GST_LOOKUP_TOKEN'),
    // Header that carries the token (e.g. x-api-key); empty sends it as a Bearer token.
    'gst_lookup_header' => env('GST_LOOKUP_HEADER'),
    'gst_lookup_timeout' => 5,
    // Masters India GST verification API; takes priority over GST_LOOKUP_URL when all four are set.
    'mastersindia' => [
        'username' => env('MASTERSINDIA_USERNAME'),
        'password' => env('MASTERSINDIA_PASSWORD'),
        'client_id' => env('MASTERSINDIA_CLIENT_ID'),
        'client_secret' => env('MASTERSINDIA_CLIENT_SECRET'),
    ],
    // Review exports are deliberately separate from a statutory filing adapter.
    'export_version' => 'zolo-gst-review-v1',
];

<?php

return [
    // Nine lifecycle checks passed; the registry also requires the installed allocation schema.
    'purchase_order_shortcut' => (bool) env('DOCUMENTS_PURCHASE_ORDER_SHORTCUT', true),
];

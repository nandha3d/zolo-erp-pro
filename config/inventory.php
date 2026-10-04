<?php

return [
    /*
     * How legacy web/console stock writers reach the stock ledger (doc 09 phase 4b/4c).
     * shadow: legacy writes stay authoritative; each request's quantity changes are recorded as a shadow movement.
     * off:    nothing is recorded (emergency switch; the ledger then drifts from the projections).
     * Authoritative cutover is done per writer method in code after clean UAT reconciliation, not by this flag.
     */
    'legacy_ledger_mode' => env('INVENTORY_LEGACY_LEDGER_MODE', 'shadow'),
];

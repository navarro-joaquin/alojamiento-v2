<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Branch Thresholds
    |--------------------------------------------------------------------------
    |
    | Fallback values used when a branch leaves its own threshold empty.
    | Amounts are in bolivianos (Bs).
    |
    */

    'cash_difference_threshold' => env('LODGING_CASH_DIFFERENCE_THRESHOLD', '10.00'),

    'expense_approval_threshold' => env('LODGING_EXPENSE_APPROVAL_THRESHOLD', '500.00'),

    'deposit_limit_nights' => (int) env('LODGING_DEPOSIT_LIMIT_NIGHTS', 7),

    /*
    |--------------------------------------------------------------------------
    | Night Audit
    |--------------------------------------------------------------------------
    |
    | A business date closes at this hour (branch local time) of the next
    | calendar day. The scheduled audit runs as the user with this e-mail,
    | which becomes the author of the nightly lodging charges.
    |
    */

    'night_audit_hour' => (int) env('LODGING_NIGHT_AUDIT_HOUR', 4),

    'night_audit_user_email' => env('LODGING_NIGHT_AUDIT_USER_EMAIL'),

];

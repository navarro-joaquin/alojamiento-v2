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

];

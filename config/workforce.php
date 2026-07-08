<?php

return [
    /*
    | Demo mode: shows the role-switcher demo bar and login bypass buttons.
    | Leave OFF in production — real password / Google login is then required,
    | and mutating actions are gated by the authenticated user's access level.
    */
    'demo' => env('WORKFORCE_DEMO', false),

    /*
    | Payroll register footer (wire-payment block), '|'-separated lines.
    | Empty → the built-in NAHSHON default. Always hidden in demo mode.
    */
    'bank_info' => env('PAYROLL_BANK_INFO', ''),
];

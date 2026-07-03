<?php

return [
    /*
    | Demo mode: shows the role-switcher demo bar and login bypass buttons.
    | Leave OFF in production — real password / Google login is then required,
    | and mutating actions are gated by the authenticated user's access level.
    */
    'demo' => env('WORKFORCE_DEMO', false),
];

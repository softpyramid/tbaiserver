<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Access request administrator
    |--------------------------------------------------------------------------
    |
    | Address that receives a mail when someone asks for access. The same
    | address is the only authenticated user who may view stored requests.
    | Leave it empty to store requests without sending mail.
    |
    */

    'admin_email' => env('ACCESS_REQUEST_ADMIN_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Seeded login password
    |--------------------------------------------------------------------------
    |
    | Password assigned to accounts created by LoginSeeder. Required outside
    | local and testing. Leave empty locally to use the default "password".
    |
    */

    'seed_login_password' => env('SEED_LOGIN_PASSWORD'),

];

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

];

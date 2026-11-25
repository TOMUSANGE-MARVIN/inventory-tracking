<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Integration with the existing inventory system
|--------------------------------------------------------------------------
|
| The existing system calls this module's API with a single bearer token.
| See docs/integration-api.md for the endpoints and payloads.
|
*/

return [
    // Shared secret the existing system sends as "Authorization: Bearer <token>". Empty: API disabled.
    'token' => env('INTEGRATION_API_TOKEN', ''),

    // Most records accepted in one call, and most updates returned per page.
    'max_batch' => 500,
    'max_updates_page' => 500,

    // The existing system's role names mapped to this module's roles (admin, branch-admin, worker).
    // Filled in once their role list is shared; unmapped roles get no access in the module.
    'roles' => [
        // 'Super Admin' => 'admin',
        // 'Branch Manager' => 'branch-admin',
        // 'Stylist' => 'worker',
    ],
];

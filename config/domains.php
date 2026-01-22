<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subdomain Configuration
    |--------------------------------------------------------------------------
    |
    | These values define the subdomains used for routing different parts
    | of the application. Admin functionality lives on the admin subdomain,
    | agent-facing features on the agents subdomain, and public pages on
    | the base domain.
    |
    */

    'admin' => env('ADMIN_DOMAIN', 'admin.beta.test'),
    'agents' => env('AGENTS_DOMAIN', 'agents.beta.test'),
    'public' => env('PUBLIC_DOMAIN', 'beta.test'),
];

<?php

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
| By default, subdomains are derived from APP_URL. You can override
| individual domains via environment variables if needed.
|
*/

$baseDomain = parse_url(env('APP_URL', 'http://beta.test'), PHP_URL_HOST) ?: 'beta.test';

return [
    'admin' => env('ADMIN_DOMAIN', 'admin.'.$baseDomain),
    'agents' => env('AGENTS_DOMAIN', 'agents.'.$baseDomain),
    'public' => env('PUBLIC_DOMAIN', $baseDomain),
];

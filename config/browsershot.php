<?php

return [
    /*
     * Configure the paths to Node.js, npm, Chrome, and other binaries.
     * Leave null to use system defaults or Browsershot's auto-detection.
     */
    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    /*
     * Enable no-sandbox mode for server environments (required on most Linux servers).
     */
    'no_sandbox' => env('BROWSERSHOT_NO_SANDBOX', false),

    /*
     * Temporary directory for HTML files. Some environments (like snap packages)
     * cannot access /tmp, so use an alternative path.
     */
    'temp_path' => env('BROWSERSHOT_TEMP_PATH'),
];

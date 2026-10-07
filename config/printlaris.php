<?php

return [
    'endpoint' => env('PRINTLARIS_ENDPOINT', 'https://app.circlaris.com/printlaris/client'),

    // Holds the connection key, admin password hash and runtime state; never served by the web server.
    'state_path' => env('PRINTLARIS_STATE_PATH', storage_path('app/printlaris')),

    'poll_interval' => 1,

    // Contact timestamps are only rewritten this often so the 1 s poll loop does not wear out the SD card.
    'state_write_interval' => 10,

    'job_history_limit' => 50,

    'ipp' => [
        'port' => (int) env('PRINTLARIS_IPP_PORT', 631),

        // Resource paths to try; IPP Everywhere printers use /ipp/print, older Brother models /ipp.
        'paths' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRINTLARIS_IPP_PATHS', '/ipp/print,/ipp'))))),
    ],

    'zebra' => [
        // Link-OS printers expose raw ZPL over TLS on 9143 when the plain port 9100 is closed.
        'port' => (int) env('PRINTLARIS_ZEBRA_PORT', 9143),

        // Comma-separated CIDRs (/22 or smaller); empty scans the subnets of the hub's own interfaces.
        'subnets' => array_values(array_filter(array_map('trim', explode(',', (string) env('PRINTLARIS_DISCOVERY_SUBNETS', ''))))),
    ],
];

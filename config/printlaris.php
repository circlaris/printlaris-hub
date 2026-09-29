<?php

return [
    'endpoint' => env('PRINTLARIS_ENDPOINT', 'https://app.circlaris.com/printlaris/client'),

    // Holds the connection key, admin password hash and runtime state; never served by the web server.
    'state_path' => env('PRINTLARIS_STATE_PATH', storage_path('app/printlaris')),

    'poll_interval' => 1,

    // Contact timestamps are only rewritten this often so the 1 s poll loop does not wear out the SD card.
    'state_write_interval' => 10,

    'job_history_limit' => 50,
];

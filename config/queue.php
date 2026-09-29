<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],
    ],
    'batching' => [],
    'failed' => [
        'driver' => 'null',
    ],
];

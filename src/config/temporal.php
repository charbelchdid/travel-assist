<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Temporal Service Address
    |--------------------------------------------------------------------------
    |
    | Temporal Frontend service address in `host:port` format.
    | In docker-compose this is `temporal:7233`.
    |
    */
    'address' => env('TEMPORAL_ADDRESS', 'temporal:7233'),

    /*
    |--------------------------------------------------------------------------
    | Temporal Namespace
    |--------------------------------------------------------------------------
    */
    'namespace' => env('TEMPORAL_NAMESPACE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Default Task Queue
    |--------------------------------------------------------------------------
    */
    'task_queue' => env('TEMPORAL_TASK_QUEUE', 'laravel-template'),
];



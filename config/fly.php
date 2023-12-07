<?php

return [
    'app_name' => env('FLY_APP_NAME'),
    'api_key' => env('FLY_API_KEY'),
    'region' => env('FLY_REGION'),
    'image' => env('FLY_IMAGE_REF'),
    'min_workers' => 2,                 // Number of base workers, always present
    'max_workers' => 10,                // Scale to a max of this many workers
    'scale_controller' => [             // Scale up as directed by this scaler
        'class' => \Fly\Worker\Scalers\JobsPerWorker::class,
        'jobs_per_machine' => 10,
    ],
    'vm' => [
        'cpu_kind' => 'shared', // vs dedicated
        'cpus' => 1,
        'memory_mb' => 1024
    ],
    'connection' => null, // default
    'queue' => null, // default
];

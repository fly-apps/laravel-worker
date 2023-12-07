# Autoscaled Queue Workers on Fly.io

This package will create Fly.io Machines machines on-demand as needed when your queue worker reaches certain thresholds.


## Installation

You can install the package via composer:

```bash
composer require fly/laravel-workers
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="config"
```

This is the contents of the published config file:

```php
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
```

## Usage

Add the `fly:work` command to your scheduler to run once a minute, on one server:

```php
$schedule->command('fly:work')
    ->onOneServer()
    ->everyMinute();
```

This command will scale up (and down) queue workers on Fly.io machines as directed by the Scaler class.

This assumes you are running your [Laravel application on Fly.io](https://fly.io/docs/laravel/) as well!

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

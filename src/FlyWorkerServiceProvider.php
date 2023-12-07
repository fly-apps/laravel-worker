<?php

namespace Fly\Worker;

use Fly\Worker\Console\FlyWorkerCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class FlyWorkerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fly.php', 'fly');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/fly.php' => config_path('fly.php'),
            ], 'config');

            $this->commands([
                FlyWorkerCommand::class
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('fly:work')
                    ->onOneServer()
                    ->everyMinute();
            });
        }
    }


}

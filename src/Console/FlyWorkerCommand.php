<?php

namespace Fly\Worker\Console;

use Fly\Worker\MachineApi;
use Fly\Worker\MachinePool;
use Illuminate\Console\Command;

class FlyWorkerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fly:work
                            {connection? : The name of the queue connection to work}
                            {--queue= : The names of the queues to work}
                            {--max-jobs=0 : The number of jobs to process before stopping}
                            {--dry-run : Perform a dry-run without taking actions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage a pool of Fly Machine queue workers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get app to use (current app?) - env var or a config here
        // Get machines within app dedicated as a worker (via metadata)
        $api = new MachineApi(config('fly.app_name'), config('fly.api_key'));
        // Determine if we have correct number of base machines and re-balance
        $pool = new MachinePool($api->listMachines());

        $this->info(sprintf("There are %s base machines", $pool->baseMachines->count()));
        $this->info(sprintf("There are %s scaled machines", $pool->scaledMachines->count()));
        $this->info(sprintf("There are %s total machines", $pool->allMachines->count()));

        $queues = $this->parseQueues();
        $connection = $this->parseConnection();

        // Destroy base workers if we have too many
        if ($pool->baseMachines->count() > config('fly.min_workers')) {
            $getRidOf = $pool->baseMachines->count() - config('fly.min_workers');
            for($d=0; $d<$getRidOf; $d++) {
                $this->info("We will destroy a base machine");
                $machine = $pool->baseMachines[$d] ?? null;
                if ($machine) {
                    $api->destroyMachine($machine['id']);
                } else {
                    $this->error("We should be deleting a base machine but did not find one");
                }
            }
        }

        // Create base workers if needed
        if ($pool->baseMachines->count() < config('fly.min_workers')) {
            $neededMachines = config('fly.min_workers') - $pool->baseMachines->count();
            for($i=0; $i<$neededMachines; $i++) {
                $this->info("We will create a new base machine");

                if ($this->option('dry-run')) {
                    continue;
                }

                $cmd = ["php", "artisan", "queue:listen",];

                if (! empty($this->option('queue'))) {
                    $cmd[] = '--queue';
                    $cmd[] = $this->option('queue');
                }

                if ($connection) {
                    $cmd[] = $this->argument('connection');
                }

                $api->createMachine([
                    "region" => config('fly.region'),
                    "config" => [
                        "image" => config('fly.image'),
                        "env" => $pool->appMachines->first()['config']['env'], // use env from an app machine
                        "auto_destroy" => false, // false for base machines
                        "guest" => config('fly.vm'),
                        "init" => [
                            "cmd" => $cmd,
                        ],
                        "metadata" => [
                            "fly_laravel_queue_machine" => "base", // vs "scaled"
                        ],
                        "restart" => [ // restart for base only
                            "max_retries" => 3,
                            "policy" => "on-failure",
                        ],
                    ],
                ]);
                usleep(250000);
            }
        }

        // Determine if we need to scale up (but never down, bc scaled-up machines stop/destroy themselves)
        //   - Perhaps keep track of how quickly we created machines, so we don't spin out of control)

        // Scale per queue
        $queueString = (count($queues) == 0) ? 'default' : implode(',', $queues);
        $connectionString = ($connection) ?: 'default';
        $this->info(sprintf("Checking scale on queue(s) '%s' on connection '%s'", $queueString, $connectionString));

        // Run queue:work for default queue if no queues specified
        if (count($queues) == 0) {
            $queues[] = '';
        }

        foreach($queues as $q) {
            $numberMachinesNeeded = $pool->getScaler(config('fly.scale_controller'))
                ->shouldScale($this->argument('connection'), $q);

            if ($numberMachinesNeeded > 0) {
                $cmd = ["php", "artisan", "queue:work", "--stop-when-empty",];

                if (! empty($q)) {
                    $cmd[] = '--queue';
                    $cmd[] = $q;
                }

                if  (! $this->option('max-jobs')) {
                    $cmd[] = '--max-jobs';
                    $cmd[] = $this->option('max-jobs');
                }

                if (!empty($connection)) {
                    $cmd[] = $connection;
                }

                for($j=0; $j<$numberMachinesNeeded; $j++) {
                    $this->info("We will create a new scaled machine");

                    if ($this->option('dry-run')) {
                        continue;
                    }

                    $api->createMachine([
                        "region" => config('fly.region'),
                        "config" => [
                            "image" => config('fly.image'),
                            "env" => $pool->appMachines->first()['config']['env'], // use env from an app machine
                            "auto_destroy" => true, // true for scaled machines
                            "guest" => config('fly.vm'),
                            "init" => [
                                "cmd" => $cmd,
                            ],
                            "metadata" => [
                                "fly_laravel_queue_machine" => "scaled", // vs "base"
                            ],
                            "restart" => [ // restart is for base only
                                "policy" => "no",
                            ],
                        ],
                    ]);
                    usleep(250000);
                }
            }
        }

        // - empty or haven't run jobs in a while
        // - second process in queue worker that kills the main pid when scale down condition
        //   is met
        // - Test --stop-when-empty logic (queue empty vs asked for queue job but didn't get one)
        //


        // TODO: Decide later if we want to force a scale down if we have more scaled machines
        //       than our current max allowed (vs letting them scale down themself)

        // TODO: We only scale down when a queue is empty, and it's possible a queue is never empty
        //       but still should reduce in scale due to low volume
        //       Correctly handling this requires saving metrics on job volume over time (redis store?)
    }

    private function parseQueues(): array
    {
        $queueRaw = (empty(trim($this->option('queue') ?? '')))
            ? config('fly.queue')
            : $this->option('queue');

        return collect(explode(',', $queueRaw))
            ->map(fn($item) => trim($item))
            ->filter()
            ->toArray();
    }

    private function parseConnection(): ?string
    {
        $connection = empty(trim($this->argument('connection')))
            ? config('fly.connection')
            : $this->argument('connection');

        if (empty($connection)) {
            return null;
        }

        return $connection;
    }
}

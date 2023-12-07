<?php

namespace Fly\Worker;

use Fly\Worker\Scalers\JobsPerWorker;
use Fly\Worker\Scalers\ShouldScaleInterface;
use Illuminate\Support\Collection;

class MachinePool
{
    public readonly Collection $allMachines;
    public readonly Collection $appMachines; // not managed by our worker
    public readonly Collection $managedMachines;
    public readonly Collection $baseMachines;
    public readonly Collection $scaledMachines;

    public function __construct(
        array $rawMachines
    )
    {
        $this->allMachines = collect($rawMachines);

        $this->appMachines = $this->allMachines->filter($this->filterAppMachines());
        $this->managedMachines = $this->allMachines->filter($this->filterQueueMachines());
        $this->baseMachines = $this->managedMachines->filter($this->filterBaseMachines());
        $this->scaledMachines = $this->managedMachines->filter($this->filterScaledMachines());
    }

    protected function filterAppMachines(): \Closure
    {
        // But is there no change here really?
        return function($machine) {
            return ($machine['config']['metadata']['fly_process_group']    ?? '') == "app"
                || ($machine['config']['metadata']['fly_platform_version'] ?? '') == "v2" ;
        };
    }

    protected function filterQueueMachines(): \Closure
    {
        return function($machine) {
            return isset($machine['config']['metadata']['fly_laravel_queue_machine']);
        };
    }

    protected function filterBaseMachines(): \Closure
    {
        return function($machine) {
            return ($machine['config']['metadata']['fly_laravel_queue_machine'] ?? '') == 'base' ;
        };
    }

    protected function filterScaledMachines(): \Closure
    {
        return function($machine) {
            return ($machine['config']['metadata']['fly_laravel_queue_machine'] ?? '') == 'scaled' ;
        };
    }

    public function getScaler(array $scalerConfig): ShouldScaleInterface
    {
        return match ($scalerConfig['class']) {
            JobsPerWorker::class => new JobsPerWorker(app('queue'), $this, $scalerConfig['jobs_per_machine']),
        };
    }
}

<?php

namespace Fly\Worker\Scalers;

use Fly\Worker\MachinePool;
use Illuminate\Contracts\Queue\Factory;

class JobsPerWorker implements ShouldScaleInterface
{
    public function __construct(
        protected Factory $queue,
        protected MachinePool $pool,
        protected int $jobsPerMachine = 10
    ){}

    public function shouldScale(?string $connection=null, ?string $queue=null): int
    {
        $totalManagedMachines = $this->pool->managedMachines->count();

        // We don't even have base machines yet (likely on first run)
        // Don't scale in this condition
        if($totalManagedMachines == 0) {
            return 0;
        }

        $currentJobsPerMachine = $this->queue->connection($connection)->size($queue) / $totalManagedMachines;
        if ($currentJobsPerMachine > $this->jobsPerMachine) {
            return (ceil($currentJobsPerMachine / $this->jobsPerMachine)) - $totalManagedMachines;
        }

        return 0;
    }
}

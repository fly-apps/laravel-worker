<?php

namespace Fly\Worker\Scalers;

interface ShouldScaleInterface
{
    public function shouldScale(?string $connection=null, string $queue=''): int;
}

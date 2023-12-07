<?php

namespace Fly\Worker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MachineApi
{
    public function __construct(
        protected string $app_name,
        protected string $api_key
    ){}

    public function listMachines(): ?array
    {
        $response = $this->getClient()->get('/machines');

        if (! $response->successful()) {
            Log::error('could not list machines for app', [
                'app' => $this->app_name,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        return $response->json();
    }

    public function createMachine(array $machine): ?array
    {
        $response = $this->getClient()->post('/machines', $machine);

        if (! $response->successful()) {
            Log::error('could not create machine for app', [
                'app' => $this->app_name,
                'status' => $response->status(),
                'body' => $response->json(),
                'headers' => $response->headers()
            ]);

            return null;
        }

        return $response->json();
    }

    public function destroyMachine(string $machineId): ?array
    {
        $response = $this->getClient()->delete('/machines/'.$machineId.'?force=true');

        if (! $response->successful()) {
            Log::error('could not destroy machine for app', [
                'app' => $this->app_name,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return null;
        }

        return $response->json();
    }

    protected function getClient()
    {
        return Http::withToken($this->api_key)
            ->asJson()
            ->acceptJson()
            ->baseUrl("https://api.machines.dev/v1/apps/".$this->app_name);
    }
}

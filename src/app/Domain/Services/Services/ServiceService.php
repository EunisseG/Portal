<?php

namespace App\Domain\Services\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Services\Models\Service;

class ServiceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Service
    {
        $service = Service::create($data);

        $this->audit->log(AuditAction::ServiceCreated, $service, null, $this->snapshot($service));

        return $service;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Service $service, array $data): Service
    {
        $old = $this->snapshot($service);

        $service->update($data);

        $this->audit->log(AuditAction::ServiceUpdated, $service, $old, $this->snapshot($service->refresh()));

        return $service;
    }

    public function delete(Service $service): void
    {
        $this->audit->log(AuditAction::ServiceDeleted, $service, $this->snapshot($service));

        $service->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Service $service): array
    {
        return [
            'client_id' => $service->client_id,
            'server_id' => $service->server_id,
            'type' => $service->type?->value,
            'name' => $service->name,
            'status' => $service->status?->value,
            'billing_cycle' => $service->billing_cycle?->value,
            'price_cents' => $service->price_cents,
            'expires_at' => $service->expires_at?->toDateString(),
        ];
    }
}

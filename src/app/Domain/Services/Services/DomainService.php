<?php

namespace App\Domain\Services\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Services\Models\Domain;

class DomainService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Domain
    {
        $domain = Domain::create($data);

        $this->audit->log(AuditAction::DomainCreated, $domain, null, $this->snapshot($domain));

        return $domain;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Domain $domain, array $data): Domain
    {
        $old = $this->snapshot($domain);

        $domain->update($data);

        $this->audit->log(AuditAction::DomainUpdated, $domain, $old, $this->snapshot($domain->refresh()));

        return $domain;
    }

    public function delete(Domain $domain): void
    {
        $this->audit->log(AuditAction::DomainDeleted, $domain, $this->snapshot($domain));

        $domain->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Domain $domain): array
    {
        return [
            'client_id' => $domain->client_id,
            'service_id' => $domain->service_id,
            'name' => $domain->name,
            'status' => $domain->status?->value,
            'expires_at' => $domain->expires_at?->toDateString(),
            'auto_renew' => $domain->auto_renew,
        ];
    }
}

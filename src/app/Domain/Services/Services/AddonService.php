<?php

namespace App\Domain\Services\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Services\Models\Addon;

class AddonService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Addon
    {
        $addon = Addon::create($data);

        $this->audit->log(AuditAction::AddonCreated, $addon, null, $this->snapshot($addon));

        return $addon;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Addon $addon, array $data): Addon
    {
        $old = $this->snapshot($addon);

        $addon->update($data);

        $this->audit->log(AuditAction::AddonUpdated, $addon, $old, $this->snapshot($addon->refresh()));

        return $addon;
    }

    public function delete(Addon $addon): void
    {
        $this->audit->log(AuditAction::AddonDeleted, $addon, $this->snapshot($addon));

        $addon->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Addon $addon): array
    {
        return [
            'service_id' => $addon->service_id,
            'name' => $addon->name,
            'status' => $addon->status?->value,
            'price_cents' => $addon->price_cents,
            'expires_at' => $addon->expires_at?->toDateString(),
        ];
    }
}

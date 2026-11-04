<?php

namespace App\Domain\Services\Services;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Services\Models\Server;

class ServerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Server
    {
        $server = Server::create($data);

        $this->audit->log(AuditAction::ServerCreated, $server, null, $server->only([
            'name', 'hostname', 'ip_address', 'is_active',
        ]));

        return $server;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Server $server, array $data): Server
    {
        $old = $server->only(['name', 'hostname', 'ip_address', 'is_active']);

        $server->update($data);

        $this->audit->log(AuditAction::ServerUpdated, $server, $old, $server->only([
            'name', 'hostname', 'ip_address', 'is_active',
        ]));

        return $server;
    }

    public function delete(Server $server): void
    {
        $this->audit->log(AuditAction::ServerDeleted, $server, $server->only([
            'name', 'hostname', 'ip_address',
        ]));

        $server->delete();
    }
}

<?php

namespace App\Domain\Services\Services;

use App\Domain\Integrations\Models\Integration;
use App\Domain\Services\Models\Service;
use App\Integrations\Actions\RecordIntegrationLog;
use App\Integrations\Providers\Whm\HostingProviderFactory;
use Illuminate\Support\Facades\Cache;

/**
 * Consulta (solo lectura) las cuentas de correo de un servicio de hosting en
 * el panel del servidor. Cachea el resultado por poco tiempo para no golpear
 * el WHM en cada carga.
 */
class ServiceMailboxViewer
{
    /** Segundos que se cachea el listado de buzones por servicio. */
    private const CACHE_TTL = 120;

    public function __construct(private readonly RecordIntegrationLog $log) {}

    /**
     * Devuelve el listado de buzones de un servicio.
     *
     * @return array{
     *     ok: bool,
     *     mailboxes: array<int, array{email:string, disk_used_mb:float, disk_quota_mb:float|null, percent:int|null}>,
     *     message: ?string
     * }
     */
    public function for(Service $service, bool $fresh = false): array
    {
        $server = $service->server;

        if (! $server || ! $server->hasPanel()) {
            return $this->result(false, [], 'El servidor de este servicio no tiene un panel configurado.');
        }

        if (empty($service->whm_username)) {
            return $this->result(false, [], 'Este servicio aún no tiene una cuenta de hosting vinculada.');
        }

        $cacheKey = 'service-mailboxes:'.$service->id;

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($service, $server) {
            $hosting = HostingProviderFactory::forServer($server);

            if (! $hosting) {
                return $this->result(false, [], 'No hay una conexión de hosting disponible para este servidor.');
            }

            $result = $hosting->listEmailAccounts((string) $service->whm_username);

            $this->log->record('whm.list_email', [
                'integration_id' => Integration::where('key', 'whm')->value('id'),
                'success' => $result->success,
                'message' => $result->success ? null : $result->message,
                'internal_reference' => 'Service:'.$service->id,
                'external_reference' => 'WhmAccount:'.$service->whm_username.'@'.$server->name,
            ]);

            if (! $result->success) {
                return $this->result(false, [], $result->message ?: 'No se pudieron obtener los correos.');
            }

            return $this->result(true, $result->data['mailboxes'] ?? [], null);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $mailboxes
     * @return array{ok: bool, mailboxes: array<int, array<string, mixed>>, message: ?string}
     */
    private function result(bool $ok, array $mailboxes, ?string $message): array
    {
        return ['ok' => $ok, 'mailboxes' => $mailboxes, 'message' => $message];
    }
}

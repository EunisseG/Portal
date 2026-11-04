<?php

namespace App\Domain\Content\Jobs;

use App\Domain\Content\Models\Maintenance;
use App\Domain\Integrations\Models\Integration;
use App\Integrations\Contracts\WhatsAppGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Avisa por WhatsApp a los clientes afectados por un mantenimiento. Se encola.
 * Usa el WhatsAppGateway (real si la integración está configurada; si no,
 * fake). La plantilla es configurable (template_maintenance).
 *
 * Destinatarios: si el mantenimiento es global, todos los clientes con número;
 * si es de un servidor concreto, solo los clientes con servicios en ese
 * servidor. La resolución vive en Maintenance::recipientClientsWithWhatsapp().
 */
class SendMaintenanceWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $maintenanceId)
    {
        $this->onQueue('integrations');
    }

    public function handle(WhatsAppGateway $whatsapp): void
    {
        $maintenance = Maintenance::find($this->maintenanceId);

        if (! $maintenance) {
            return;
        }

        $integration = Integration::where('key', 'whatsapp')->first();
        $template = $integration?->config['template_maintenance'] ?? 'maintenance_notice';

        $when = $maintenance->starts_at?->translatedFormat('d M Y H:i') ?? '';

        foreach ($maintenance->recipientClientsWithWhatsapp() as $client) {
            $whatsapp->sendMessage($client->whatsapp, $template, [
                'name' => $client->user?->name ?? 'Cliente',
                'title' => $maintenance->title,
                'date' => $when,
            ]);
        }
    }
}

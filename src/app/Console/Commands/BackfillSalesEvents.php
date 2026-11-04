<?php

namespace App\Console\Commands;

use App\Domain\Sales\Services\SalesEventRecorder;
use App\Domain\Services\Models\Addon;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Service;
use Illuminate\Console\Command;

/**
 * Crea un evento de VENTA NUEVA por cada servicio, dominio y complemento que ya
 * existía antes de implementar el registro de eventos comerciales. Usa la fecha
 * de creación (created_at) de cada registro como fecha del evento.
 *
 * Es idempotente: el recorder no duplica si ya existe un new_sale para ese
 * registro, así que se puede ejecutar varias veces sin problema. Las
 * renovaciones pasadas NO se pueden recuperar (no quedó rastro); solo se
 * cuentan a partir de ahora.
 */
class BackfillSalesEvents extends Command
{
    protected $signature = 'sales:backfill';

    protected $description = 'Genera eventos de venta nueva para servicios, dominios y complementos ya existentes (idempotente).';

    public function handle(SalesEventRecorder $recorder): int
    {
        $created = 0;

        $this->info('Procesando servicios...');
        Service::with('client')->chunkById(200, function ($services) use ($recorder, &$created) {
            foreach ($services as $service) {
                if ($recorder->recordNewSale($service, $service->created_at)) {
                    $created++;
                }
            }
        });

        $this->info('Procesando dominios...');
        Domain::with('client')->chunkById(200, function ($domains) use ($recorder, &$created) {
            foreach ($domains as $domain) {
                if ($recorder->recordNewSale($domain, $domain->created_at)) {
                    $created++;
                }
            }
        });

        $this->info('Procesando complementos...');
        Addon::with('service.client')->chunkById(200, function ($addons) use ($recorder, &$created) {
            foreach ($addons as $addon) {
                if ($recorder->recordNewSale($addon, $addon->created_at)) {
                    $created++;
                }
            }
        });

        $this->info('Listo. Eventos de venta nueva creados: '.$created.'.');

        return self::SUCCESS;
    }
}

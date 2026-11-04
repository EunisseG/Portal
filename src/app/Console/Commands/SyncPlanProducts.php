<?php

namespace App\Console\Commands;

use App\Domain\Services\Models\Plan;
use App\Domain\Services\Services\PlanProductSync;
use Illuminate\Console\Command;

/**
 * Genera (o actualiza) el producto espejo de todos los planes existentes.
 * Útil para reflejar en el catálogo de productos los planes creados antes de
 * activar la sincronización automática.
 */
class SyncPlanProducts extends Command
{
    protected $signature = 'plans:sync-products';

    protected $description = 'Crea/actualiza el producto espejo de cada plan existente.';

    public function handle(PlanProductSync $sync): int
    {
        $plans = Plan::with('prices')->get();

        foreach ($plans as $plan) {
            $sync->sync($plan);
            $this->line('Sincronizado: '.$plan->name);
        }

        $this->info($plans->count().' plan(es) sincronizados con el catálogo de productos.');

        return self::SUCCESS;
    }
}

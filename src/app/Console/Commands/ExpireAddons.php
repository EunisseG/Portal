<?php

namespace App\Console\Commands;

use App\Domain\Billing\Services\PricingService;
use App\Domain\Services\Enums\AddonStatus;
use App\Domain\Services\Models\Addon;
use Illuminate\Console\Command;

/**
 * Suspende los complementos RECURRENTES cuyo vencimiento ya pasó, para que
 * "caduquen" por su propio ciclo sin intervención manual. Un complemento
 * suspendido deja de contar en el total del pedido (ver PricingService) y se
 * puede renovar desde el detalle del pedido.
 *
 * No toca complementos de pago único (billing_cycle null) ni los ya cancelados.
 */
class ExpireAddons extends Command
{
    protected $signature = 'addons:expire';

    protected $description = 'Suspende los complementos recurrentes vencidos (caducan según su propio ciclo).';

    public function handle(PricingService $pricing): int
    {
        $today = now()->startOfDay()->toDateString();

        $expired = Addon::query()
            ->whereNotNull('billing_cycle')         // solo recurrentes
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->where('status', AddonStatus::Active->value)
            ->with('service')
            ->get();

        $count = 0;
        $services = collect();

        foreach ($expired as $addon) {
            $addon->update(['status' => AddonStatus::Suspended->value]);
            $count++;
            if ($addon->service) {
                $services->put($addon->service->id, $addon->service);
            }
        }

        // Recalcula el total de los pedidos afectados (el complemento vencido
        // ya no se cobra).
        foreach ($services as $service) {
            $pricing->recalculate($service->load('addons'));
        }

        $this->info("Complementos recurrentes suspendidos por vencimiento: {$count}.");

        return self::SUCCESS;
    }
}

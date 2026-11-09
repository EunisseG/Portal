<?php

namespace App\Console\Commands;

use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\PaymentGateway;
use Illuminate\Console\Command;

/**
 * Genera órdenes de pago para los pedidos (Service) próximos a vencer dentro de
 * una ventana de días, para que el cliente pueda renovar pagando en línea. No
 * duplica: si el pedido ya tiene una orden pendiente, la omite.
 */
class GenerateRenewalPaymentOrders extends Command
{
    protected $signature = 'payment-orders:generate-renewals {--days=15 : Ventana en días antes del vencimiento}';

    protected $description = 'Genera órdenes de pago para los pedidos próximos a vencer (renovación).';

    public function handle(PaymentOrderService $orders): int
    {
        $days = (int) $this->option('days');
        $from = now()->startOfDay();
        $to = now()->startOfDay()->addDays($days);

        $services = Service::query()
            ->whereNotNull('expires_at')
            ->where('status', '!=', ServiceStatus::Cancelled->value)
            ->whereBetween('expires_at', [$from->toDateString(), $to->toDateString()])
            ->get();

        $gateway = app(PaymentGateway::class);
        $generated = 0;

        foreach ($services as $service) {
            $hasPending = PaymentOrder::where('service_id', $service->id)
                ->where('status', PaymentOrderStatus::Pending->value)
                ->exists();

            if ($hasPending) {
                continue; // ya hay una orden abierta para este pedido
            }

            // Si la orden de este ciclo ya se pagó (se generó dentro de la
            // ventana de renovación), no se genera otra.
            $windowStart = $service->expires_at->copy()->subDays($days)->startOfDay();
            $paidThisCycle = PaymentOrder::where('service_id', $service->id)
                ->where('status', PaymentOrderStatus::Paid->value)
                ->where('created_at', '>=', $windowStart)
                ->exists();

            if ($paidThisCycle) {
                continue;
            }

            try {
                $orders->createForService($service, $gateway);
                $generated++;
            } catch (\Throwable $e) {
                $this->warn('Pedido #'.$service->id.': '.$e->getMessage());
            }
        }

        $this->info('Órdenes de pago de renovación generadas: '.$generated.'.');

        return self::SUCCESS;
    }
}

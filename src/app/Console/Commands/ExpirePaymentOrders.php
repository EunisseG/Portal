<?php

namespace App\Console\Commands;

use App\Domain\Billing\Enums\PaymentOrderStatus;
use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentOrderService;
use Illuminate\Console\Command;

/**
 * Expira las órdenes de pago pendientes cuyo plazo (5 días) ya venció. Una
 * orden expirada deja de ser pagable; al dar "Renovar"/"Pagar" se genera una
 * nueva. No toca las ya pagadas, canceladas o expiradas.
 */
class ExpirePaymentOrders extends Command
{
    protected $signature = 'payment-orders:expire';

    protected $description = 'Expira las órdenes de pago pendientes que ya superaron su plazo (5 días).';

    public function handle(PaymentOrderService $orders): int
    {
        $overdue = PaymentOrder::query()
            ->where('status', PaymentOrderStatus::Pending->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($overdue as $order) {
            $orders->expire($order);
        }

        $this->info('Órdenes de pago expiradas: '.$overdue->count().'.');

        return self::SUCCESS;
    }
}

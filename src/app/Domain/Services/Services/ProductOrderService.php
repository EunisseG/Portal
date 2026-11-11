<?php

namespace App\Domain\Services\Services;

use App\Domain\Billing\Models\PaymentOrder;
use App\Domain\Billing\Services\PaymentOrderService;
use App\Domain\Billing\Services\PricingService;
use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\BillingCycle;
use App\Domain\Services\Enums\DomainStatus;
use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Models\Service;
use App\Integrations\Contracts\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Convierte un producto del catálogo en un pedido (Service) y genera su orden
 * de pago. Lo usan el alta de cliente (servicio inicial) y el flujo de compra
 * del propio cliente, para que toda compra nazca igual: pedido + orden.
 *
 * Si los pagos están en modo manual la orden se crea sin enlace de pago; en
 * modo automático se conecta con la pasarela (ver PaymentOrderService).
 */
class ProductOrderService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly PaymentOrderService $orders,
    ) {}

    /**
     * Extensiones de dominio que registramos sin costo (salvo premium). Es la
     * misma regla que usa el formulario de pedidos del admin.
     *
     * @var list<string>
     */
    public const FREE_TLDS = ['.com', '.com.mx'];

    /** ¿La extensión del dominio es de las que registramos gratis? */
    public static function isFreeTld(string $name): bool
    {
        $name = strtolower(trim($name));

        foreach (self::FREE_TLDS as $tld) {
            if (str_ends_with($name, $tld)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Crea el pedido a partir del producto y su orden de pago. Para un producto
     * recurrente usa el precio e IVA del ciclo elegido (o el primero
     * disponible); para uno de pago único, su precio plano.
     *
     * Para un pedido de hosting acepta el dominio asociado:
     *  - ['mode' => 'existing', 'id' => <dominio del cliente sin servicio>]
     *  - ['mode' => 'new', 'name' => 'ejemplo.com', 'origin' => 'owned'|'purchase']
     *
     * @param  array{mode:string, id?:?int, name?:?string, origin?:?string}|null  $domain
     */
    public function place(Client $client, Product $product, string $cycleValue, ServiceStatus $status, ?array $domain = null): PaymentOrder
    {
        $service = DB::transaction(function () use ($client, $product, $cycleValue, $status, $domain) {
            $service = $this->createService($client, $product, $cycleValue, $status);

            if ($domain !== null && $service->type === ServiceType::Hosting) {
                $this->attachDomain($service, $domain);
            }

            return $service;
        });

        $gateway = app(PaymentGateway::class);

        try {
            return $this->orders->createForService($service, $gateway);
        } catch (\Throwable $e) {
            // Un fallo de la pasarela no debe dejar el pedido sin orden: se
            // crea sin enlace y el enlace se genera al momento de pagar.
            report($e);

            return $this->orders->createForService($service, null);
        }
    }

    /**
     * Liga el dominio al pedido de hosting: uno que el cliente ya tiene en el
     * portal o uno nuevo. Un dominio que se compra con nosotros fuera de las
     * extensiones gratuitas NO se cobra aquí (el cliente no conoce el precio):
     * queda anotado para que el equipo lo cotice y lo agregue al pedido.
     *
     * @param  array{mode:string, id?:?int, name?:?string, origin?:?string}  $data
     */
    private function attachDomain(Service $service, array $data): void
    {
        if (($data['mode'] ?? '') === 'existing' && ! empty($data['id'])) {
            // Solo un dominio del mismo cliente que aún no esté en otro servicio.
            Domain::where('id', $data['id'])
                ->where('client_id', $service->client_id)
                ->whereNull('service_id')
                ->update(['service_id' => $service->id]);

            return;
        }

        if (($data['mode'] ?? '') !== 'new' || empty($data['name'])) {
            return;
        }

        $name = strtolower(trim((string) $data['name']));

        if (($data['origin'] ?? 'owned') !== 'purchase') {
            $note = 'El cliente ya tiene el dominio en otro registrador (sin costo).';
        } elseif (self::isFreeTld($name)) {
            $note = 'Comprado con nosotros (gratis).';
        } else {
            $note = 'El cliente pidió comprarlo con nosotros. Pendiente de cotización: agrégalo al pedido con su precio.';
        }

        Domain::create([
            'client_id' => $service->client_id,
            'service_id' => $service->id,
            'name' => $name,
            'status' => DomainStatus::Pending->value,
            'auto_renew' => false,
            'notes' => $note,
        ]);
    }

    /** Crea el pedido (Service) con su desglose de precios calculado. */
    private function createService(Client $client, Product $product, string $cycleValue, ServiceStatus $status): Service
    {
        $product->loadMissing('prices');

        $cycle = BillingCycle::tryFrom($cycleValue) ?? BillingCycle::Monthly;

        // Recurrente: precio e IVA del ciclo elegido (o el primero disponible).
        // Pago único: precio plano con el IVA del producto.
        $pricing = $product->pricingFor($cycle->value);
        $unitCents = $pricing['unit_cents'];
        $taxRate = $pricing['tax_rate'];
        $billingCycle = $pricing['cycle'] ?? BillingCycle::Monthly;
        $months = $pricing['cycle']?->months() ?? 1;

        $taxCents = (int) round($unitCents * $taxRate / 100);
        $totalCents = $unitCents + $taxCents;

        // El tipo del servicio sale del tipo del producto (p. ej. «hosting»);
        // si el producto tiene un tipo personalizado se queda como «Otro».
        $type = $product->serviceType();

        $service = Service::create([
            'client_id' => $client->id,
            'product_id' => $product->id,
            'plan_id' => $product->plan_id,
            'type' => $type->value,
            'name' => $product->name,
            'status' => $status->value,
            'billing_cycle' => $billingCycle->value,
            'unit_price_cents' => $unitCents,
            'tax_rate' => $taxRate,
            'total_cents' => $totalCents,
            'price_cents' => $totalCents,
            'currency' => $product->currency,
            'starts_at' => now()->toDateString(),
            'expires_at' => now()->addMonths($months)->toDateString(),
        ]);

        // Completa el desglose (subtotal/IVA/total) del pedido.
        $this->pricing->recalculate($service);

        return $service->refresh();
    }
}

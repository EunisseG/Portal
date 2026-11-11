<?php

namespace App\Livewire\Client\Orders;

use App\Domain\Services\Enums\ServiceStatus;
use App\Domain\Services\Enums\ServiceType;
use App\Domain\Services\Models\Domain;
use App\Domain\Services\Models\Product;
use App\Domain\Services\Services\ProductOrderService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pedido del cliente: elige un producto del catálogo (y su ciclo si es
 * recurrente) y se genera su pedido junto con la orden de pago. Si el producto
 * es de hosting, pregunta por el dominio igual que el formulario del admin:
 * uno que ya tiene en el portal, o uno nuevo (que ya posee en otro registrador
 * o que quiere comprar con nosotros).
 *
 * En modo manual la orden queda sin pasarela (el cliente sube su comprobante);
 * en automático puede pagarla en línea desde su recibo.
 */
class OrderCreate extends Component
{
    public string $productId = '';

    public string $cycle = '';

    // Dominio (solo hosting).
    /** 'existing' = uno que ya tiene en el portal, 'new' = uno nuevo. */
    public string $domainMode = 'new';

    public string $domainId = '';

    public string $domainName = '';

    /** Origen del dominio nuevo: 'owned' = ya lo tiene, 'purchase' = comprar con nosotros. */
    public string $domainOrigin = 'owned';

    public function mount(): void
    {
        abort_unless(auth()->user()->canClient('client.orders.create'), 403);
        abort_unless(auth()->user()->clientAccount() !== null, 403);
    }

    /** Al cambiar de producto se reinicia el ciclo y las respuestas de dominio. */
    public function updatedProductId(): void
    {
        $product = $this->selectedProduct();
        $this->cycle = $product?->isRecurring()
            ? (string) ($product->prices->first()?->billing_cycle?->value ?? '')
            : '';

        $this->resetDomainAnswers();
    }

    private function resetDomainAnswers(): void
    {
        // Si el cliente ya tiene dominios libres, lo natural es ligar uno.
        $this->domainMode = $this->availableDomains()->isNotEmpty() ? 'existing' : 'new';
        $this->domainId = '';
        $this->domainName = '';
        $this->domainOrigin = 'owned';
        $this->resetErrorBag(['domainId', 'domainName']);
    }

    private function selectedProduct(): ?Product
    {
        if ($this->productId === '') {
            return null;
        }

        return Product::with('prices')
            ->where('is_active', true)
            // Las extensiones de dominio no se piden como servicio suelto.
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', Product::TYPE_DOMAIN))
            ->find($this->productId);
    }

    /** ¿El producto elegido es de hosting (lleva dominio)? */
    private function usesDomain(?Product $product): bool
    {
        return $product !== null && $product->type === ServiceType::Hosting->value;
    }

    /** Dominios del cliente que todavía no están ligados a un servicio. */
    private function availableDomains()
    {
        $client = auth()->user()->clientAccount();

        return $client
            ? Domain::where('client_id', $client->id)->whereNull('service_id')->orderBy('name')->get(['id', 'name'])
            : collect();
    }

    public function place(ProductOrderService $orders): mixed
    {
        $user = auth()->user();
        abort_unless($user->canClient('client.orders.create'), 403);

        $client = $user->clientAccount();
        abort_unless($client !== null, 403);

        $product = $this->selectedProduct();

        if (! $product) {
            $this->addError('productId', 'Selecciona un producto.');

            return null;
        }

        // Un producto recurrente exige un ciclo válido de los que tiene precio.
        if ($product->isRecurring() && $product->prices->isNotEmpty()) {
            $valid = $product->prices->contains(fn ($p) => $p->billing_cycle->value === $this->cycle);
            if (! $valid) {
                $this->addError('cycle', 'Selecciona un ciclo de facturación.');

                return null;
            }
        }

        $domain = null;
        if ($this->usesDomain($product)) {
            $domain = $this->validatedDomain($client->id);
            if ($domain === null) {
                return null; // los errores ya quedaron en pantalla
            }
        }

        // El pedido nace Pendiente: el equipo lo activa cuando se confirma el pago.
        $order = $orders->place($client, $product, $this->cycle, ServiceStatus::Pending, $domain);

        session()->flash('status', 'Pedido creado. Aquí está tu orden de pago.');

        return $this->redirect(route('client.payment-orders.receipt', $order));
    }

    /**
     * Valida las respuestas de dominio y devuelve los datos listos para el
     * servicio, o null si hay errores (que se muestran en el formulario).
     *
     * @return array{mode:string, id?:int, name?:string, origin?:string}|null
     */
    private function validatedDomain(int $clientId): ?array
    {
        if ($this->domainMode === 'existing') {
            $domain = $this->domainId === '' ? null : Domain::where('id', $this->domainId)
                ->where('client_id', $clientId)
                ->whereNull('service_id')
                ->first();

            if (! $domain) {
                $this->addError('domainId', 'Selecciona uno de tus dominios o cambia a «Dominio nuevo».');

                return null;
            }

            return ['mode' => 'existing', 'id' => $domain->id];
        }

        $name = strtolower(trim($this->domainName));

        if ($name === '') {
            $this->addError('domainName', 'Escribe el nombre del dominio.');

            return null;
        }

        // Formato básico: etiquetas con letras/números/guiones y una extensión.
        if (preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $name) !== 1) {
            $this->addError('domainName', 'Escribe un dominio válido, por ejemplo: miempresa.com');

            return null;
        }

        if (Domain::where('client_id', $clientId)->where('name', $name)->exists()) {
            $this->addError('domainName', 'Ya tienes ese dominio registrado. Elígelo en «Ya tengo un dominio en el portal».');

            return null;
        }

        return [
            'mode' => 'new',
            'name' => $name,
            'origin' => $this->domainOrigin === 'purchase' ? 'purchase' : 'owned',
        ];
    }

    public function render(): View
    {
        $products = Product::with(['prices', 'category'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', Product::TYPE_DOMAIN))
            ->orderBy('name')
            ->get();

        $selected = $this->selectedProduct();

        return view('livewire.client.orders.order-create', [
            'products' => $products,
            'selected' => $selected,
            'usesDomain' => $this->usesDomain($selected),
            'availableDomains' => $this->availableDomains(),
            // ¿La extensión escrita se cobra? (solo informativo para el cliente)
            'domainNeedsQuote' => $this->domainMode === 'new'
                && $this->domainOrigin === 'purchase'
                && trim($this->domainName) !== ''
                && ! ProductOrderService::isFreeTld($this->domainName),
        ])->layout('layouts.app', ['area' => 'client']);
    }
}

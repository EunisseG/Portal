<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Services\Models\Product;
use App\Domain\Services\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class ProductIndex extends Component
{
    /** Pestaña activa: 'products' o 'categories'. */
    #[Url(as: 'tab')]
    public string $tab = 'products';

    #[Url(as: 'q')]
    public string $search = '';

    // --- Modal de producto ---
    public bool $showProductModal = false;
    public ?int $editingProductId = null;
    public string $productType = 'custom';
    public ?int $productCategoryId = null;
    public string $productName = '';

    /** Modo de cobro: 'one_time' o 'recurring'. */
    public string $billingMode = 'one_time';

    /** Precio para pago único (decimal). */
    public string $oneTimePrice = '';

    /** IVA (%) del pago único. */
    public string $oneTimeTaxRate = '16';

    /** Filtro de estado del listado: '' (todos), 'active' o 'inactive'. */
    #[Url(as: 'estado')]
    public string $statusFilter = '';

    /**
     * Ciclos habilitados (recurrente): [billing_cycle => bool].
     *
     * @var array<string, bool>
     */
    public array $enabled = [];

    /** @var array<string, string> */
    public array $unitPrice = [];

    /** @var array<string, string> */
    public array $taxRate = [];

    // Prioridades editables por fila: [id => valor].
    /** @var array<int, int> */
    public array $priorities = [];

    // --- Modal de categoría ---
    public bool $showCategoryModal = false;
    public ?int $editingCategoryId = null;
    public string $categoryTitle = '';
    public string $categoryIconUrl = '';
    public string $categoryDescription = '';

    public function mount(): void
    {
        Gate::authorize('plans.manage');
        $this->loadPriorities();
    }

    /** Precarga las prioridades editables desde la BD. */
    private function loadPriorities(): void
    {
        $this->priorities = Product::orderBy('priority')->pluck('priority', 'id')->all();
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['products', 'categories'], true) ? $tab : 'products';
    }

    // ===================== Productos =====================

    /** Inicializa los arreglos de ciclos (deshabilitados, IVA 16%). */
    private function resetCycleInputs(): void
    {
        $this->enabled = [];
        $this->unitPrice = [];
        $this->taxRate = [];
        foreach (\App\Domain\Services\Enums\BillingCycle::cases() as $cycle) {
            $this->enabled[$cycle->value] = false;
            $this->unitPrice[$cycle->value] = '';
            $this->taxRate[$cycle->value] = '16';
        }
    }

    public function openProductModal(): void
    {
        Gate::authorize('plans.manage');
        $this->reset('editingProductId', 'productName', 'oneTimePrice');
        $this->oneTimeTaxRate = '16';
        $this->productType = (string) (array_key_first($this->manualTypeOptions()) ?? 'custom');
        $this->billingMode = 'one_time';
        $this->productCategoryId = ProductCategory::query()->value('id');
        $this->resetCycleInputs();
        $this->resetValidation();
        $this->showProductModal = true;
    }

    public function editProduct(int $id): void
    {
        Gate::authorize('plans.manage');
        $product = Product::with('prices')->findOrFail($id);
        // Los productos que vienen de un plan se editan desde el plan.
        abort_if($product->isFromPlan(), 403);
        $this->editingProductId = $product->id;
        // Un valor antiguo que ya no existe se edita como "Otro".
        $this->productType = array_key_exists((string) $product->type, $this->manualTypeOptions())
            ? (string) $product->type
            : \App\Domain\Services\Enums\ProductType::Custom->value;
        $this->billingMode = $product->billing_mode?->value ?? 'one_time';
        $this->productCategoryId = $product->category_id;
        $this->productName = $product->name;
        $this->oneTimePrice = $product->price_cents ? number_format($product->price_cents / 100, 2, '.', '') : '';
        $this->oneTimeTaxRate = (string) (float) ($product->tax_rate ?? 0);

        $this->resetCycleInputs();
        foreach ($product->prices as $price) {
            $c = $price->billing_cycle->value;
            $this->enabled[$c] = true;
            $this->unitPrice[$c] = number_format($price->unit_price_cents / 100, 2, '.', '');
            $this->taxRate[$c] = (string) (float) $price->tax_rate;
        }

        $this->resetValidation();
        $this->showProductModal = true;
    }

    public function saveProduct(): void
    {
        Gate::authorize('plans.manage');

        $data = $this->validate([
            // Hosting no se da de alta aquí: esos productos nacen de un plan
            // (Sistema → Servidores / Planes).
            'productType' => ['required', 'string', Rule::in(array_keys($this->manualTypeOptions()))],
            'billingMode' => ['required', Rule::enum(\App\Domain\Services\Enums\BillingMode::class)],
            'productCategoryId' => ['nullable', 'integer', 'exists:product_categories,id'],
            'productName' => ['required', 'string', 'max:255'],
        ], [
            'productType.in' => 'Los planes de hosting se dan de alta en Sistema → Servidores / Planes; aquí elige Dominio, Correo, Licencia u Otro.',
        ]);

        // Precios según el modo de cobro.
        $oneTimeCents = 0;
        $oneTimeTax = 0.0;
        $cyclePrices = [];

        if ($data['billingMode'] === \App\Domain\Services\Enums\BillingMode::OneTime->value) {
            if ($this->oneTimePrice === '' || ! is_numeric($this->oneTimePrice) || (float) $this->oneTimePrice < 0) {
                $this->addError('oneTimePrice', 'Ingresa un precio válido para el pago único.');

                return;
            }
            if (! is_numeric($this->oneTimeTaxRate) || (float) $this->oneTimeTaxRate < 0 || (float) $this->oneTimeTaxRate > 100) {
                $this->addError('oneTimeTaxRate', 'Ingresa un IVA válido (0 a 100).');

                return;
            }
            $oneTimeCents = \App\Support\Money::fromDecimal($this->oneTimePrice)->amountInCents;
            $oneTimeTax = (float) $this->oneTimeTaxRate;
        } else {
            foreach (\App\Domain\Services\Enums\BillingCycle::cases() as $cycle) {
                $c = $cycle->value;
                if (! ($this->enabled[$c] ?? false)) {
                    continue;
                }
                $unit = $this->unitPrice[$c] ?? '';
                $tax = $this->taxRate[$c] ?? '16';
                if ($unit === '' || ! is_numeric($unit) || (float) $unit < 0) {
                    $this->addError("unitPrice.$c", 'Precio inválido para '.$cycle->label().'.');

                    return;
                }
                if (! is_numeric($tax) || (float) $tax < 0) {
                    $this->addError("taxRate.$c", 'IVA inválido para '.$cycle->label().'.');

                    return;
                }
                $cyclePrices[$c] = [
                    'unit_price_cents' => \App\Support\Money::fromDecimal($unit)->amountInCents,
                    'tax_rate' => (float) $tax,
                ];
            }

            if ($cyclePrices === []) {
                $this->addError('enabled', 'Habilita al menos un ciclo con su precio.');

                return;
            }
            // El precio "plano" se mantiene con el ciclo más común (mensual) para
            // compatibilidad con usos que leen price_cents.
            $oneTimeCents = $cyclePrices['monthly']['unit_price_cents'] ?? reset($cyclePrices)['unit_price_cents'];
        }

        $payload = [
            'name' => $data['productName'],
            'type' => $data['productType'],
            'billing_mode' => $data['billingMode'],
            'category_id' => $data['productCategoryId'] ?: null,
            'price_cents' => $oneTimeCents,
            // IVA del pago único; en recurrentes va por ciclo (product_prices).
            'tax_rate' => $oneTimeTax,
            'currency' => 'MXN',
        ];

        if ($this->editingProductId) {
            $product = Product::findOrFail($this->editingProductId);
            abort_if($product->isFromPlan(), 403);
            $product->update($payload);
            session()->flash('status', 'Producto actualizado.');
        } else {
            $product = Product::create($payload + [
                'is_active' => true,
                'priority' => (int) (Product::max('priority') ?? 0) + 1,
            ]);
            session()->flash('status', 'Producto creado.');
        }

        // Sincroniza los precios por ciclo (reemplazo completo).
        $product->prices()->delete();
        foreach ($cyclePrices as $c => $vals) {
            $product->prices()->create([
                'billing_cycle' => $c,
                'unit_price_cents' => $vals['unit_price_cents'],
                'tax_rate' => $vals['tax_rate'],
                'currency' => 'MXN',
            ]);
        }

        $this->showProductModal = false;
    }

    public function deleteProduct(int $id): void
    {
        Gate::authorize('plans.manage');
        $product = Product::findOrFail($id);
        // No se borra a mano un producto espejo de plan; se gestiona desde el plan.
        abort_if($product->isFromPlan(), 403);
        $product->delete();
        unset($this->priorities[$id]);
        session()->flash('status', 'Producto eliminado.');
    }

    /** Guarda las prioridades editadas en la tabla. */
    public function updatePriorities(): void
    {
        Gate::authorize('plans.manage');

        foreach ($this->priorities as $id => $value) {
            $value = max(1, (int) $value);
            Product::whereKey($id)->update(['priority' => $value]);
        }

        $this->loadPriorities();
        session()->flash('status', 'Prioridades actualizadas.');
    }

    /**
     * Habilita o deshabilita un producto. Si es el espejo de un plan, cambia
     * también el plan para que no se vuelva a activar al guardar el plan.
     */
    public function toggleProduct(int $id): void
    {
        Gate::authorize('plans.manage');

        $product = Product::findOrFail($id);
        $active = ! $product->is_active;

        $product->update(['is_active' => $active]);
        if ($product->isFromPlan()) {
            \App\Domain\Services\Models\Plan::whereKey($product->plan_id)->update(['is_active' => $active]);
        }

        session()->flash('status', $active ? 'Producto habilitado.' : 'Producto deshabilitado.');
    }

    /** Filtra el listado por estado ('' = todos). */
    public function filterStatus(string $status): void
    {
        $this->statusFilter = in_array($status, ['active', 'inactive'], true) ? $status : '';
    }

    /**
     * Opciones de "Funciona como" para dar de alta a mano: todas menos hosting
     * (los planes de hosting se crean en Servidores / Planes).
     *
     * @return array<string, string>
     */
    private function manualTypeOptions(): array
    {
        return \App\Domain\Services\Enums\ProductType::manualOptions();
    }

    // ===================== Categorías =====================

    public function openCategoryModal(): void
    {
        Gate::authorize('plans.manage');
        $this->reset('editingCategoryId', 'categoryTitle', 'categoryIconUrl', 'categoryDescription');
        $this->resetValidation();
        $this->showCategoryModal = true;
    }

    public function editCategory(int $id): void
    {
        Gate::authorize('plans.manage');
        $category = ProductCategory::findOrFail($id);
        $this->editingCategoryId = $category->id;
        $this->categoryTitle = $category->title;
        $this->categoryIconUrl = (string) $category->icon_url;
        $this->categoryDescription = (string) $category->description;
        $this->resetValidation();
        $this->showCategoryModal = true;
    }

    public function saveCategory(): void
    {
        Gate::authorize('plans.manage');

        $data = $this->validate([
            'categoryTitle' => ['required', 'string', 'max:255'],
            'categoryIconUrl' => ['nullable', 'string', 'max:2048'],
            'categoryDescription' => ['nullable', 'string', 'max:5000'],
        ]);

        $payload = [
            'title' => $data['categoryTitle'],
            'icon_url' => $data['categoryIconUrl'] ?: null,
            'description' => $data['categoryDescription'] ?: null,
        ];

        if ($this->editingCategoryId) {
            ProductCategory::whereKey($this->editingCategoryId)->update($payload);
            session()->flash('status', 'Categoría actualizada.');
        } else {
            ProductCategory::create($payload);
            session()->flash('status', 'Categoría creada.');
        }

        $this->showCategoryModal = false;
    }

    public function deleteCategory(int $id): void
    {
        Gate::authorize('plans.manage');
        ProductCategory::whereKey($id)->delete();
        session()->flash('status', 'Categoría eliminada.');
    }

    public function render(): View
    {
        $products = collect();
        $categories = collect();

        if ($this->tab === 'categories') {
            $categories = ProductCategory::query()->withCount('products')->orderBy('title')->get();
        } else {
            $products = Product::query()
                ->with(['category', 'prices'])
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
                ->orderBy('priority')
                ->orderBy('name')
                ->get();

            // Asegura que las prioridades editables incluyan filas nuevas sin
            // pisar los valores que el usuario esté editando.
            foreach ($products as $product) {
                if (! array_key_exists($product->id, $this->priorities)) {
                    $this->priorities[$product->id] = $product->priority;
                }
            }
        }

        // KPIs y gráfica de distribución de productos por estado (habilitado/deshabilitado).
        $activeCount = (int) Product::where('is_active', true)->count();
        $inactiveCount = (int) Product::where('is_active', false)->count();

        $kpis = [
            'active' => $activeCount,
            'inactive' => $inactiveCount,
            'total' => $activeCount + $inactiveCount,
        ];

        $chart = [
            'labels' => ['Habilitados', 'Deshabilitados'],
            'data' => [$activeCount, $inactiveCount],
        ];

        return view('livewire.admin.services.product-index', [
            'products' => $products,
            'categories' => $categories,
            'typeOptions' => $this->manualTypeOptions(),
            'billingModeOptions' => \App\Domain\Services\Enums\BillingMode::options(),
            'cycles' => \App\Domain\Services\Enums\BillingCycle::cases(),
            'categoryOptions' => ProductCategory::orderBy('title')->pluck('title', 'id'),
            'kpis' => $kpis,
            'chart' => $chart,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}

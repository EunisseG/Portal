<?php

namespace App\Livewire\Admin\Services;

use App\Domain\Services\Enums\DiscountType;
use App\Domain\Services\Models\Promotion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PromotionIndex extends Component
{
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $discount_type = 'percent';

    public int $discount_value = 0;

    public ?string $starts_at = null;

    public ?string $ends_at = null;

    public bool $is_active = true;

    public function mount(): void
    {
        Gate::authorize('plans.manage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('promotions', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('plans.manage');

        $promotion = Promotion::findOrFail($id);
        $this->editingId = $promotion->id;
        $this->code = $promotion->code;
        $this->name = $promotion->name;
        $this->discount_type = $promotion->discount_type->value;
        $this->discount_value = $promotion->discount_value;
        $this->starts_at = $promotion->starts_at?->toDateString();
        $this->ends_at = $promotion->ends_at?->toDateString();
        $this->is_active = $promotion->is_active;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize('plans.manage');

        $data = $this->validate();
        $data['code'] = strtoupper($data['code']);

        if ($this->editingId) {
            Promotion::whereKey($this->editingId)->update($data);
            session()->flash('status', 'Promoción actualizada.');
        } else {
            Promotion::create($data);
            session()->flash('status', 'Promoción creada.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('plans.manage');

        Promotion::whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Promoción eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'code', 'name', 'discount_value', 'starts_at', 'ends_at');
        $this->discount_type = 'percent';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $promotions = Promotion::query()->orderByDesc('id')->get();

        return view('livewire.admin.services.promotion-index', [
            'promotions' => $promotions,
            'discountTypes' => DiscountType::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}

<?php

namespace App\Livewire\Admin\System;

use App\Domain\System\Models\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CurrencyIndex extends Component
{
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '$';

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
            'code' => ['required', 'string', 'size:3', Rule::unique('currencies', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:8'],
            'is_active' => ['boolean'],
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('plans.manage');

        $currency = Currency::findOrFail($id);
        $this->editingId = $currency->id;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->symbol = $currency->symbol;
        $this->is_active = $currency->is_active;
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
            Currency::whereKey($this->editingId)->update($data);
            session()->flash('status', 'Moneda actualizada.');
        } else {
            Currency::create($data);
            session()->flash('status', 'Moneda creada.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('plans.manage');

        Currency::whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Moneda eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'code', 'name', 'symbol', 'is_active');
        $this->symbol = '$';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $currencies = Currency::query()->orderBy('code')->get();

        return view('livewire.admin.system.currency-index', [
            'currencies' => $currencies,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}

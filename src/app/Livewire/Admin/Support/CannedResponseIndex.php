<?php

namespace App\Livewire\Admin\Support;

use App\Domain\Tickets\Models\CannedResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class CannedResponseIndex extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public string $body = '';

    public int $sort_order = 0;

    public function mount(): void
    {
        Gate::authorize('tickets.manage');
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function edit(int $id): void
    {
        Gate::authorize('tickets.manage');

        $response = CannedResponse::findOrFail($id);
        $this->editingId = $response->id;
        $this->title = $response->title;
        $this->body = $response->body;
        $this->sort_order = $response->sort_order;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        Gate::authorize('tickets.manage');

        $data = $this->validate();

        if ($this->editingId) {
            CannedResponse::whereKey($this->editingId)->update($data);
            session()->flash('status', 'Respuesta preparada actualizada.');
        } else {
            CannedResponse::create($data);
            session()->flash('status', 'Respuesta preparada creada.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('tickets.manage');

        CannedResponse::whereKey($id)->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Respuesta preparada eliminada.');
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'title', 'body', 'sort_order');
        $this->resetValidation();
    }

    public function render(): View
    {
        $responses = CannedResponse::query()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get();

        return view('livewire.admin.support.canned-response-index', [
            'responses' => $responses,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}

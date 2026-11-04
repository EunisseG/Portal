<?php

namespace App\Livewire\Admin\Kb;

use App\Domain\Content\Models\KbCategory;
use App\Domain\Content\Services\KbService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class KbCategoryForm extends Component
{
    public ?KbCategory $category = null;

    public string $name = '';
    public string $description = '';
    public int $sort_order = 0;

    public function mount(?KbCategory $category = null): void
    {
        if ($category && $category->exists) {
            Gate::authorize('update', KbCategory::class);
            $this->category = $category;
            $this->name = $category->name;
            $this->description = (string) $category->description;
            $this->sort_order = $category->sort_order;
        } else {
            Gate::authorize('create', KbCategory::class);
        }
    }

    public function isEditing(): bool
    {
        return $this->category !== null;
    }

    public function save(KbService $kb): mixed
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        if ($this->isEditing()) {
            $kb->updateCategory($this->category, $data);
            session()->flash('status', 'Categoría actualizada.');
        } else {
            $kb->createCategory($data);
            session()->flash('status', 'Categoría creada.');
        }

        return $this->redirect(route('admin.kb.categories.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.admin.kb.kb-category-form')
            ->layout('layouts.app', ['area' => 'admin']);
    }
}

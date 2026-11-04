<?php

namespace App\Livewire\Admin\Kb;

use App\Domain\Content\Models\KbCategory;
use App\Domain\Content\Services\KbService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class KbCategoryIndex extends Component
{
    use WithPagination;

    public function mount(): void
    {
        Gate::authorize('viewAny', KbCategory::class);
    }

    public function delete(int $categoryId, KbService $kb): void
    {
        Gate::authorize('delete', KbCategory::class);
        $category = KbCategory::findOrFail($categoryId);
        $kb->deleteCategory($category);
        session()->flash('status', 'Categoría eliminada (con sus artículos).');
    }

    public function render(): View
    {
        $categories = KbCategory::query()
            ->withCount('articles')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.admin.kb.kb-category-index', [
            'categories' => $categories,
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}

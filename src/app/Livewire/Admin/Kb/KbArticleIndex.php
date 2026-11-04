<?php

namespace App\Livewire\Admin\Kb;

use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use App\Domain\Content\Services\KbService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class KbArticleIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'cat')]
    public string $categoryFilter = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', KbArticle::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function delete(int $articleId, KbService $kb): void
    {
        Gate::authorize('delete', KbArticle::class);
        $article = KbArticle::findOrFail($articleId);
        $kb->deleteArticle($article);
        session()->flash('status', 'Artículo eliminado.');
    }

    public function render(): View
    {
        $articles = KbArticle::query()
            ->with('category')
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('kb_category_id', $this->categoryFilter))
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.admin.kb.kb-article-index', [
            'articles' => $articles,
            'categories' => KbCategory::orderBy('name')->get(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}

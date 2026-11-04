<?php

namespace App\Livewire\Admin\News;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\News;
use App\Domain\Content\Services\NewsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class NewsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', News::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $newsId, NewsService $news): void
    {
        Gate::authorize('delete', News::class);
        $item = News::findOrFail($newsId);
        $news->delete($item);
        session()->flash('status', 'Noticia eliminada.');
    }

    public function render(): View
    {
        $items = News::query()
            ->with('author')
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', "%{$this->search}%"))
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.admin.news.news-index', [
            'items' => $items,
            'statusOptions' => ContentStatus::options(),
        ])->layout('layouts.app', ['area' => 'admin']);
    }
}

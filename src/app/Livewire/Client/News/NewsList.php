<?php

namespace App\Livewire\Client\News;

use App\Domain\Content\Models\News;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class NewsList extends Component
{
    use WithPagination;

    public function render(): View
    {
        $items = News::query()
            ->visible()
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('livewire.client.news.news-list', [
            'items' => $items,
        ])->layout('layouts.app', ['area' => 'client']);
    }
}

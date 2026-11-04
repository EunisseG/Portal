<?php

namespace App\Livewire\Client\News;

use App\Domain\Content\Models\News;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NewsShow extends Component
{
    public News $news;

    public function mount(string $slug): void
    {
        // Solo noticias visibles (publicadas y con fecha llegada).
        $this->news = News::visible()->where('slug', $slug)->firstOrFail();
    }

    public function render(): View
    {
        return view('livewire.client.news.news-show')
            ->layout('layouts.app', ['area' => 'client']);
    }
}

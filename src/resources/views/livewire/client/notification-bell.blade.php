<div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
    {{-- Botón campana --}}
    <button type="button" @click="open = ! open"
            class="relative flex h-9 w-9 items-center justify-center rounded-full text-white/90 transition hover:bg-white/15"
            aria-label="Notificaciones">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-4 text-white ring-2 ring-indigo-600">
                {{ $unreadCount > 9 ? '9+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Panel desplegable --}}
    <div x-show="open" x-cloak @click.outside="open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl bg-white text-slate-700 shadow-lg ring-1 ring-slate-900/10">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <span class="text-sm font-semibold text-slate-900">Notificaciones</span>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-indigo-600 hover:underline">
                    Marcar todas como leídas
                </button>
            @endif
        </div>

        <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $unread = $notification->read_at === null;
                    $type = $data['type'] ?? 'general';
                    $iconBg = match ($type) {
                        'ticket' => 'bg-blue-50 text-blue-600',
                        'maintenance' => 'bg-amber-50 text-amber-600',
                        'news' => 'bg-indigo-50 text-indigo-600',
                        default => 'bg-slate-100 text-slate-500',
                    };
                @endphp
                <button type="button" wire:click="open('{{ $notification->id }}')"
                        class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-slate-50 {{ $unread ? 'bg-indigo-50/40' : '' }}">
                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $iconBg }}">
                        @if ($type === 'ticket')
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                        @elseif ($type === 'maintenance')
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26"/></svg>
                        @elseif ($type === 'news')
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"/></svg>
                        @else
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                        @endif
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-slate-900">{{ $data['title'] ?? 'Notificación' }}</span>
                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $data['message'] ?? '' }}</span>
                        <span class="mt-0.5 block text-[11px] text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                    </span>
                    @if ($unread)
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-red-500"></span>
                    @endif
                </button>
            @empty
                <div class="px-4 py-10 text-center text-sm text-slate-400">
                    No tienes notificaciones.
                </div>
            @endforelse
        </div>
    </div>
</div>

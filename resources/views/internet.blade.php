<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 pb-32">
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">🌐 {{ __('game.internet_title') }}</h2>
            <p class="text-sm text-text-secondary mt-1">{{ __('game.internet_subtitle') }}</p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-accent-green/30 bg-accent-green/10 px-4 py-3 text-sm text-accent-green">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-accent-red/30 bg-accent-red/10 px-4 py-3 text-sm text-accent-red">{{ session('error') }}</div>
        @endif

        {{-- Post as the manager --}}
        <div class="rounded-xl border border-border-default bg-surface-800 p-4 mb-6">
            <form method="POST" action="{{ route('game.internet.post', $game->id) }}">
                @csrf
                <textarea name="text" rows="2" maxlength="280" required
                          placeholder="{{ __('game.internet_placeholder') }}"
                          class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body placeholder:text-text-faint focus:outline-none focus:ring-2 focus:ring-accent-blue/50"></textarea>
                <div class="flex items-center justify-between mt-2">
                    <span class="text-xs text-text-muted">{{ __('game.internet_limit', ['used' => $postedToday, 'limit' => $dailyLimit]) }}</span>
                    <button type="submit"
                            @disabled($postedToday >= $dailyLimit)
                            class="rounded-lg bg-accent-blue px-5 py-2 text-sm font-bold text-white hover:brightness-110 transition disabled:opacity-40">
                        {{ __('game.internet_publish') }}
                    </button>
                </div>
            </form>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-2 overflow-x-auto pb-2 mb-4">
            @foreach($tabs as $t)
                <a href="{{ route('game.internet', ['gameId' => $game->id, 'tab' => $t]) }}"
                   class="whitespace-nowrap rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wide transition {{ $tab === $t ? 'bg-accent-blue text-white' : 'bg-surface-800 text-text-muted hover:text-text-primary border border-border-default' }}">
                    {{ __('game.internet_tab_' . $t) }}
                </a>
            @endforeach
        </div>

        {{-- Timeline --}}
        @if($posts->isEmpty())
            <div class="rounded-xl border border-border-default bg-surface-800 p-8 text-center">
                <p class="text-4xl mb-3">🕸️</p>
                <p class="text-sm text-text-muted">{{ __('game.internet_empty') }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach($posts as $post)
                    <article class="rounded-xl border border-border-default bg-surface-800 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-accent-blue/15 text-lg font-bold text-accent-blue">
                                {{ mb_substr($post->author_name ?? '?', 0, 1) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2 flex-wrap">
                                    <span class="text-sm font-bold text-text-primary truncate">{{ $post->author_name }}</span>
                                    <span class="text-xs text-text-faint truncate">{{ $post->author_handle }}</span>
                                    <span class="text-[11px] text-text-faint">· {{ $post->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="mt-1 text-sm text-text-body whitespace-pre-line">{{ $post->text }}</p>
                                <div class="mt-2 flex items-center gap-4 text-xs text-text-faint">
                                    <span>❤️ {{ number_format($post->likes ?? 0, 0, ',', '.') }}</span>
                                    @if($post->replies->isNotEmpty())
                                        <span>💬 {{ $post->replies->count() }}</span>
                                    @endif
                                </div>
                                @if($post->replies->isNotEmpty())
                                    <div class="mt-3 space-y-2 border-l-2 border-border-default pl-3">
                                        @foreach($post->replies as $reply)
                                            <div class="text-sm">
                                                <span class="font-bold text-text-primary">{{ $reply->author_name }}</span>
                                                <span class="text-xs text-text-faint">{{ $reply->author_handle }}</span>
                                                <p class="text-text-body">{{ $reply->text }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            <a href="{{ route('show-game', $game->id) }}" class="text-sm text-accent-blue underline">← {{ __('game.back_to_dashboard') }}</a>
        </div>
    </div>
</x-app-layout>

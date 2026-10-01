@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4 flex items-center justify-between gap-4">
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                    🐦 {{ __('game.social_title', [], 'es') === 'game.social_title' ? 'Red Social' : __('game.social_title') }}
                </h2>
                <p class="text-sm text-text-secondary mt-1">
                    {{ __('game.social_subtitle', [], 'es') === 'game.social_subtitle' ? 'Lo que dice la afición de ti...' : __('game.social_subtitle') }}
                </p>
            </div>
            <div class="text-right shrink-0">
                <div class="text-xs uppercase tracking-wide text-text-faint">{{ __('game.board_confidence', [], 'es') === 'game.board_confidence' ? 'Confianza directiva' : __('game.board_confidence') }}</div>
                <div class="text-2xl font-bold {{ $boardConfidence >= 50 ? 'text-green-500' : ($boardConfidence >= 30 ? 'text-yellow-500' : 'text-red-500') }}">
                    {{ $boardConfidence }}%
                </div>
            </div>
        </div>

        @if(session('press_done'))
            <div class="mb-4 p-3 rounded-lg bg-accent-blue/10 border border-accent-blue/30 text-sm text-text-primary">
                {{ __('game.press_published', [], 'es') === 'game.press_published' ? 'Tus declaraciones ya están circulando por la red...' : __('game.press_published') }}
            </div>
        @endif

        @if($boardConfidence < 30)
            <div class="mb-4 p-4 rounded-lg bg-red-500/10 border border-red-500/40">
                <p class="font-bold text-red-500">⚠️ {{ __('game.board_warning', [], 'es') === 'game.board_warning' ? 'La directiva está perdiendo la paciencia. ¡Cuidado con lo que dices!' : __('game.board_warning') }}</p>
            </div>
        @endif

        <div class="flex gap-4 mb-4 text-sm">
            <span class="text-green-500 font-semibold">👍 {{ $positiveCount }}</span>
            <span class="text-red-500 font-semibold">👎 {{ $negativeCount }}</span>
        </div>

        <div class="space-y-3">
            @forelse($posts as $post)
                <div class="p-4 rounded-xl bg-surface-800 border {{ $post->sentiment < 0 ? 'border-red-500/30' : ($post->sentiment > 0 ? 'border-green-500/30' : 'border-border-default') }}">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold">
                            {{ substr($post->author_name, 0, 1) }}
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold text-text-primary text-sm">{{ $post->author_name }}</div>
                            <div class="text-text-faint text-xs">{{ $post->author_handle }}</div>
                        </div>
                        @if($post->sentiment > 0)
                            <span class="text-green-500 text-lg">👍</span>
                        @elseif($post->sentiment < 0)
                            <span class="text-red-500 text-lg">👎</span>
                        @endif
                    </div>
                    <p class="text-text-primary text-sm leading-relaxed">{{ $post->text }}</p>
                    <div class="flex items-center gap-4 mt-2 text-xs text-text-faint">
                        <span>❤️ {{ $post->likes }}</span>
                        <span>{{ $post->created_at->diffForHumans() }}</span>
                        @if($post->context === 'sacked')
                            <span class="font-bold text-red-500 uppercase">Destitución</span>
                        @elseif($post->context === 'board_warning')
                            <span class="font-bold text-yellow-500 uppercase">Rumor</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center rounded-xl bg-surface-800 border border-border-default">
                    <p class="text-4xl mb-2">🐦</p>
                    <p class="text-text-secondary">
                        {{ __('game.social_empty', [], 'es') === 'game.social_empty' ? 'Aún no hay actividad. Juega partidos y atiende a la prensa para que la afición hable de ti.' : __('game.social_empty') }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>

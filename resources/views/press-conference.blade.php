@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-2xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                🎤 {{ app()->getLocale() === 'es' ? 'Rueda de prensa' : 'Press conference' }}
            </h2>
            <p class="text-sm text-text-secondary mt-1">
                {{ $match->homeTeam?->name }} {{ $match->home_score }} - {{ $match->away_score }} {{ $match->awayTeam?->name }}
            </p>
            <p class="text-xs text-text-faint mt-1">
                {{ app()->getLocale() === 'es'
                    ? 'Cuidado con lo que dices: la afición está en las redes y la directiva también las lee.'
                    : 'Watch what you say: the fans are on social media, and so is the board.' }}
            </p>
        </div>

        @if($alreadyDone)
            <div class="p-6 rounded-xl bg-surface-800 border border-border-default text-center">
                <p class="text-4xl mb-2">✅</p>
                <p class="text-text-primary font-semibold">
                    {{ app()->getLocale() === 'es' ? 'Ya atendiste a la prensa tras este partido.' : 'You already faced the press after this match.' }}
                </p>
                <a href="{{ route('game.social', $game->id) }}" class="inline-block mt-4 px-4 py-2 rounded-lg bg-accent-blue text-white text-sm font-semibold">
                    🐦 {{ app()->getLocale() === 'es' ? 'Ver reacciones' : 'See reactions' }}
                </a>
            </div>
        @else
            <form method="POST" action="{{ route('game.press.submit', [$game->id, $match->id]) }}" class="space-y-3">
                @csrf
                @foreach($options as $option)
                    <label class="block p-4 rounded-xl bg-surface-800 border border-border-default hover:border-accent-blue cursor-pointer transition-colors">
                        <div class="flex items-start gap-3">
                            <input type="radio" name="statement_key" value="{{ $option['key'] }}" class="mt-1" required
                                @if(isset($option['player'])) data-player="{{ $option['player']['id'] }}" @endif>
                            <div class="flex-1">
                                <div class="font-semibold text-text-primary">{{ $option['label'] }}</div>
                                @if(isset($option['player']))
                                    <input type="hidden" name="player_id_{{ $option['key'] }}" value="{{ $option['player']['id'] }}">
                                @endif
                            </div>
                        </div>
                    </label>
                @endforeach

                <input type="hidden" name="player_id" id="press-player-id" value="">

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 px-4 py-3 rounded-xl bg-accent-blue text-white font-bold uppercase tracking-wide">
                        {{ app()->getLocale() === 'es' ? 'Hacer declaraciones' : 'Make statement' }}
                    </button>
                    <a href="{{ route('show-game', $game->id) }}" class="px-4 py-3 rounded-xl bg-surface-700 text-text-secondary font-semibold">
                        {{ app()->getLocale() === 'es' ? 'Pasar' : 'Skip' }}
                    </a>
                </div>
            </form>

            <script>
                document.querySelectorAll('input[name="statement_key"]').forEach(radio => {
                    radio.addEventListener('change', () => {
                        document.getElementById('press-player-id').value = radio.dataset.player || '';
                    });
                });
            </script>
        @endif
    </div>
</x-app-layout>

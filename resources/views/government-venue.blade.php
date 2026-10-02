<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 py-8">
        @if($offer)
            @php $meta = $offer->metadata ?? []; @endphp
            <div class="bg-surface-800 border border-accent-blue/40 rounded-2xl p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-widest text-accent-blue mb-2">
                    {{ __('game.gov_venue_badge') }}
                </p>
                <h1 class="font-heading text-2xl sm:text-3xl font-bold uppercase text-text-primary mb-4">
                    {{ $offer->title }}
                </h1>
                <p class="text-text-body leading-relaxed mb-6">{{ $offer->message }}</p>

                <div class="flex flex-col sm:flex-row gap-3">
                    <form method="POST" action="{{ route('game.government-venue.accept', ['gameId' => $game->id]) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="notification_id" value="{{ $offer->id }}">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                            @foreach(($meta['stadiums'] ?? []) as $i => $stadium)
                                <label class="cursor-pointer">
                                    <input type="radio" name="stadium" value="{{ $stadium }}" @checked($i === 0) class="peer sr-only">
                                    <div class="rounded-xl border-2 border-border-default peer-checked:border-accent-gold bg-surface-900 p-4 text-center transition-colors">
                                        <p class="font-bold text-text-primary">🏟️ {{ $stadium }}</p>
                                        <p class="text-[10px] uppercase tracking-widest text-text-muted mt-1">{{ __('game.gov_venue_free') }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                        <button type="submit" class="w-full px-6 py-3 bg-accent-gold hover:brightness-110 text-surface-900 font-bold uppercase tracking-wide rounded-xl">
                            {{ __('game.gov_venue_accept') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('game.government-venue.reject', ['gameId' => $game->id]) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="notification_id" value="{{ $offer->id }}">
                        <button type="submit" class="w-full px-6 py-3 bg-surface-700 hover:bg-surface-600 text-text-body font-semibold uppercase tracking-wide rounded-xl">
                            {{ __('game.gov_venue_reject') }}
                        </button>
                    </form>
                </div>
            </div>
        @else
            <div class="bg-surface-800 border border-border-default rounded-2xl p-8 text-center">
                <p class="text-text-muted">{{ __('game.gov_venue_no_offer') }}</p>
                <a href="{{ route('show-game', ['gameId' => $game->id]) }}" class="inline-block mt-4 px-6 py-2 bg-accent-blue/10 hover:bg-accent-blue/20 text-accent-blue font-semibold rounded-lg">
                    {{ __('game.gov_friendly_back') }}
                </a>
            </div>
        @endif
    </div>
</x-app-layout>

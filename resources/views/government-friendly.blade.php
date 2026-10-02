<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 py-8">
        @if($offer)
            @php $meta = $offer->metadata ?? []; @endphp
            <div class="bg-surface-800 border border-accent-gold/40 rounded-2xl p-6 sm:p-8">
                <p class="text-xs font-bold uppercase tracking-widest text-accent-gold mb-2">
                    {{ __('game.gov_friendly_badge') }}
                </p>
                <h1 class="font-heading text-2xl sm:text-3xl font-bold uppercase text-text-primary mb-4">
                    {{ $offer->title }}
                </h1>
                <p class="text-text-body leading-relaxed mb-6">{{ $offer->message }}</p>

                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-8">
                    <div class="rounded-xl bg-surface-900 p-4">
                        <dt class="text-[10px] uppercase tracking-widest text-text-muted mb-1">{{ __('game.gov_friendly_government') }}</dt>
                        <dd class="font-bold text-text-primary">{{ $meta['government'] ?? '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-surface-900 p-4">
                        <dt class="text-[10px] uppercase tracking-widest text-text-muted mb-1">{{ __('game.gov_friendly_opponent') }}</dt>
                        <dd class="font-bold text-text-primary">{{ $meta['opponent_name'] ?? '—' }}</dd>
                    </div>
                    <div class="rounded-xl bg-surface-900 p-4">
                        <dt class="text-[10px] uppercase tracking-widest text-text-muted mb-1">{{ __('game.gov_friendly_fee') }}</dt>
                        <dd class="font-bold text-accent-gold">{{ number_format($meta['amount'] ?? 0, 0, ',', '.') }} €</dd>
                    </div>
                </dl>

                <div class="flex flex-col sm:flex-row gap-3">
                    <form method="POST" action="{{ route('game.government-friendly.accept', ['gameId' => $game->id]) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="notification_id" value="{{ $offer->id }}">
                        <button type="submit" class="w-full px-6 py-3 bg-accent-gold hover:brightness-110 text-surface-900 font-bold uppercase tracking-wide rounded-xl">
                            {{ __('game.gov_friendly_accept') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('game.government-friendly.reject', ['gameId' => $game->id]) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="notification_id" value="{{ $offer->id }}">
                        <button type="submit" class="w-full px-6 py-3 bg-surface-700 hover:bg-surface-600 text-text-body font-semibold uppercase tracking-wide rounded-xl">
                            {{ __('game.gov_friendly_reject') }}
                        </button>
                    </form>
                </div>
            </div>
        @else
            <div class="bg-surface-800 border border-border-default rounded-2xl p-8 text-center">
                <p class="text-text-muted">{{ __('game.gov_friendly_no_offer') }}</p>
                <a href="{{ route('show-game', ['gameId' => $game->id]) }}" class="inline-block mt-4 px-6 py-2 bg-accent-blue/10 hover:bg-accent-blue/20 text-accent-blue font-semibold rounded-lg">
                    {{ __('game.gov_friendly_back') }}
                </a>
            </div>
        @endif
    </div>
</x-app-layout>

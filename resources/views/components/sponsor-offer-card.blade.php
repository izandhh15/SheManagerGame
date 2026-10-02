@props([
    'offer',   // array: id, sponsor_name, tier, annual_value_cents, contract_seasons, is_renewal
    'game',    // Game model (for the accept/reject routes)
])

@use(App\Support\Money)

<div class="bg-surface-700 border border-border-default rounded-xl overflow-hidden flex flex-col">
    {{-- Header: sponsor + its stature --}}
    <div class="px-5 pt-5 pb-3">
        <div class="mb-2 flex items-center gap-2">
            <span class="inline-block text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-accent-gold/10 text-accent-gold">
                {{ __('club.commercial.tier_badge_' . ($offer['tier'] ?? 'local')) }}
            </span>
            @if($offer['is_renewal'] ?? false)
                <span class="inline-block text-[10px] uppercase tracking-wide font-semibold px-2 py-0.5 rounded-md bg-accent-blue/10 text-accent-blue">
                    {{ __('club.commercial.renewal_badge') }}
                </span>
            @endif
        </div>
        <div class="font-heading text-xl font-bold text-text-primary truncate">{{ $offer['sponsor_name'] }}</div>
    </div>

    {{-- Terms --}}
    <div class="px-5 pb-4 space-y-1.5 text-sm">
        <div class="flex items-baseline justify-between gap-3">
            <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted">{{ __('club.commercial.annual_value') }}</span>
            <span class="font-heading text-base font-bold text-accent-green tabular-nums">{{ Money::format($offer['annual_value_cents']) }}</span>
        </div>
        <div class="flex items-baseline justify-between gap-3">
            <span class="font-semibold uppercase tracking-wide text-[10px] text-text-muted">{{ __('club.commercial.contract_length') }}</span>
            <span class="text-text-primary">{{ trans_choice('club.commercial.seasons', $offer['contract_seasons'], ['count' => $offer['contract_seasons']]) }}</span>
        </div>
    </div>

    {{-- Accept / reject --}}
    <div class="px-5 pb-5 mt-auto flex gap-2">
        <form method="POST" action="{{ route('game.club.commercial.sponsors.accept', $game->id) }}" class="flex-1"
              onsubmit="return confirm(@js(__('club.commercial.accept_confirm', ['sponsor' => $offer['sponsor_name'], 'value' => Money::format($offer['annual_value_cents'])])))">
            @csrf
            <input type="hidden" name="deal_id" value="{{ $offer['id'] }}">
            <x-primary-button color="green" size="sm" class="w-full">
                {{ __('club.commercial.accept_button') }}
            </x-primary-button>
        </form>
        <form method="POST" action="{{ route('game.club.commercial.sponsors.reject', $game->id) }}"
              onsubmit="return confirm(@js(__('club.commercial.reject_confirm', ['sponsor' => $offer['sponsor_name']])))">
            @csrf
            <input type="hidden" name="deal_id" value="{{ $offer['id'] }}">
            <x-primary-button color="red" size="sm">
                {{ __('club.commercial.reject_button') }}
            </x-primary-button>
        </form>
    </div>
</div>

<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-32">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.venue_org_title') }}</h2>
            <p class="text-sm text-text-secondary mt-1">{{ __('game.venue_org_subtitle', ['team' => $userTeam->name]) }}</p>
            <div class="mt-3 inline-flex items-center gap-2 rounded-lg border border-accent-green/30 bg-accent-green/10 px-4 py-2">
                <span class="text-xs uppercase tracking-wide text-text-muted">{{ __('game.venue_org_budget') }}</span>
                <span class="text-lg font-bold text-accent-green">€{{ number_format($federationBudget, 0, ',', '.') }}</span>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-accent-green/30 bg-accent-green/10 px-4 py-3 text-sm text-accent-green">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-accent-red/30 bg-accent-red/10 px-4 py-3 text-sm text-accent-red">
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-accent-red/30 bg-accent-red/10 px-4 py-3 text-sm text-accent-red">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- Waiting on the user's own club (dual mode) --}}
        @if($awaitingClub->isNotEmpty())
            <div class="mb-8">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">⏳ {{ __('game.venue_org_awaiting_title') }}</h3>
                <div class="space-y-2">
                    @foreach($awaitingClub as $match)
                        <div class="flex items-center gap-3 rounded-lg border border-accent-orange/30 bg-surface-800 px-4 py-3">
                            <x-team-crest :team="$match->awayTeam" class="w-8 h-8" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-text-body truncate">{{ $userTeam->name }} vs {{ $match->awayTeam->name }}</p>
                                <p class="text-xs text-text-muted">{{ \Carbon\Carbon::parse($match->scheduled_date)->format('d/m/Y') }} · {{ $match->round_name }}</p>
                                <p class="text-xs text-accent-orange mt-0.5">{{ __('game.venue_org_awaiting_hint') }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($pending->isEmpty())
            <div class="rounded-xl border border-border-default bg-surface-800 p-8 text-center">
                <p class="text-4xl mb-3">🏟️</p>
                <p class="text-sm font-semibold text-text-body">{{ __('game.venue_org_none_title') }}</p>
                <p class="text-xs text-text-muted mt-1">{{ __('game.venue_org_none_hint') }}</p>
            </div>
        @else
            <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">📋 {{ __('game.venue_org_pending_title', ['count' => $pending->count()]) }}</h3>
            <div class="space-y-6">
                @foreach($pending as $match)
                    <form method="POST" action="{{ route('game.national-venues.store', $game->id) }}"
                          class="rounded-xl border border-border-default bg-surface-800 p-5 space-y-4"
                          x-data="{ venueType: 'national', offer: 0, maxBudget: {{ $federationBudget }} }">
                        @csrf
                        <input type="hidden" name="match_id" value="{{ $match->id }}">

                        <div class="flex items-center gap-3">
                            <x-team-crest :team="$match->awayTeam" class="w-10 h-10" />
                            <div class="flex-1 min-w-0">
                                <p class="text-base font-bold text-text-primary">{{ $userTeam->name }} vs {{ $match->awayTeam->name }}</p>
                                <p class="text-xs text-text-muted">
                                    {{ \Carbon\Carbon::parse($match->scheduled_date)->format('d/m/Y') }} ·
                                    {{ $match->round_name }}
                                </p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full bg-accent-red/15 text-accent-red font-semibold">⚠ {{ __('game.venue_org_no_venue') }}</span>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-text-body mb-2">🏟️ {{ __('game.friendly_venue_type') }}</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="cursor-pointer">
                                    <input type="radio" name="venue_type" value="national" x-model="venueType" class="sr-only peer">
                                    <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-center text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10 peer-checked:font-semibold">
                                        {{ __('game.friendly_venue_national') }}
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="venue_type" value="club" x-model="venueType" class="sr-only peer">
                                    <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-center text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10 peer-checked:font-semibold">
                                        {{ __('game.friendly_venue_club') }}
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="venue_type" value="mens" x-model="venueType" class="sr-only peer">
                                    <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-center text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10 peer-checked:font-semibold">
                                        {{ __('game.friendly_venue_mens') }}
                                    </span>
                                </label>
                                <label class="cursor-pointer">
                                    <input type="radio" name="venue_type" value="neutral" x-model="venueType" class="sr-only peer">
                                    <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-center text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10 peer-checked:font-semibold">
                                        {{ __('game.friendly_venue_neutral') }}
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div x-show="venueType === 'national'">
                            <label class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_stadium') }}</label>
                            <select name="stadium"
                                    class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                                @foreach($stadiums as $country => $list)
                                    <optgroup label="{{ $country }}">
                                        @foreach($list as $s)
                                            <option value="{{ $s['stadium'] }}" @selected($defaultStadium && $s['stadium'] === $defaultStadium['stadium'])>
                                                {{ $s['stadium'] }} — {{ $s['city'] ?? '' }} ({{ number_format($s['capacity'], 0, ',', '.') }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p class="text-xs text-text-muted mt-1">{{ __('game.venue_org_free_hint') }}</p>
                        </div>

                        <div x-show="venueType === 'club'">
                            <label class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_venue_club_label') }}</label>
                            <select name="club_team_id"
                                    class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                                @foreach($clubStadiums as $country => $list)
                                    <optgroup label="{{ $country }}">
                                        @foreach($list as $s)
                                            <option value="{{ $s['team_id'] }}">
                                                {{ $s['stadium'] }} — {{ $s['team_name'] }} ({{ number_format($s['capacity'], 0, ',', '.') }})
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p class="text-xs text-text-muted mt-1">{{ __('game.venue_org_club_hint') }}</p>
                        </div>

                        <div x-show="venueType === 'mens'">
                            <label class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_venue_mens_label') }}</label>
                            <select name="mens_stadium"
                                    class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                                @foreach($mensStadiums as $s)
                                    <option value="{{ $s['stadium'] }}">
                                        {{ $s['stadium'] }} — {{ $s['club'] }} ({{ number_format($s['capacity'], 0, ',', '.') }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-text-muted mt-1">{{ __('game.venue_org_mens_hint', ['min' => number_format($rebateMinOffer, 0, ',', '.'), 'pct' => (int) round($rebateShare * 100)]) }}</p>
                        </div>

                        <div x-show="venueType === 'neutral'">
                            <div class="rounded-lg border border-border-default bg-surface-900 px-4 py-3 text-sm text-text-body">
                                <p class="font-semibold">{{ $neutralVenueName }} ({{ number_format($neutralVenueCapacity, 0, ',', '.') }})</p>
                                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_venue_neutral_hint') }}</p>
                            </div>
                        </div>

                        {{-- Offer: pay whatever you want, within the federation budget --}}
                        <div x-show="venueType === 'club' || venueType === 'mens'" x-cloak
                             class="rounded-lg border border-accent-green/30 bg-accent-green/5 p-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-semibold text-text-body">💰 {{ __('game.venue_org_offer') }}</label>
                                <span class="text-base font-bold text-accent-green">€<span x-text="Number(offer).toLocaleString('es-ES')"></span></span>
                            </div>
                            <input type="range" name="offer" x-model.number="offer" min="0" :max="maxBudget" step="100000"
                                   class="w-full accent-green-500">
                            <div class="flex justify-between text-[11px] text-text-muted mt-1">
                                <span>€0</span>
                                <span>{{ __('game.venue_org_budget_left', ['amount' => number_format($federationBudget, 0, ',', '.')]) }}</span>
                            </div>
                            <p class="text-xs text-text-muted mt-2">{{ __('game.venue_org_offer_hint') }}</p>
                        </div>

                        <button type="submit"
                                class="w-full rounded-lg bg-accent-blue px-4 py-3 text-sm font-bold uppercase tracking-wide text-white hover:brightness-110 transition">
                            {{ __('game.venue_org_submit') }}
                        </button>
                    </form>
                @endforeach
            </div>
        @endif

        <div class="mt-6">
            <a href="{{ route('show-game', $game->id) }}" class="text-sm text-accent-blue underline">← {{ __('game.back_to_dashboard') }}</a>
        </div>
    </div>
</x-app-layout>

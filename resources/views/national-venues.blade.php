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

        {{-- Calendar: pick which match to request a stadium for --}}
        @if($pending->isNotEmpty())
            <div class="mb-8 rounded-xl border border-border-default bg-surface-800 p-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-1">📅 {{ __('game.venue_org_calendar_title') }}</h3>
                <p class="text-xs text-text-muted mb-4">{{ __('game.venue_org_calendar_hint') }}</p>
                <div class="grid md:grid-cols-2 gap-6">
                    @foreach($calendarMonths as $month)
                        <div>
                            <p class="text-sm font-bold text-text-primary text-center mb-2">{{ $month['label'] }}</p>
                            <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-text-muted mb-1">
                                <span>{{ __('game.cal_mon') }}</span><span>{{ __('game.cal_tue') }}</span><span>{{ __('game.cal_wed') }}</span><span>{{ __('game.cal_thu') }}</span><span>{{ __('game.cal_fri') }}</span><span>{{ __('game.cal_sat') }}</span><span>{{ __('game.cal_sun') }}</span>
                            </div>
                            @foreach($month['weeks'] as $week)
                                <div class="grid grid-cols-7 gap-1">
                                    @foreach($week as $day)
                                        @if($day['inMonth'])
                                            @if(count($day['matches']) > 0)
                                                <a href="#match-{{ $day['matches'][0]['id'] }}"
                                                   title="{{ $day['matches'][0]['rival'] }}"
                                                   class="relative rounded-lg border-2 border-accent-blue bg-accent-blue/15 px-1 py-1.5 text-sm font-bold text-text-primary hover:bg-accent-blue/30 transition">
                                                    {{ $day['day'] }}
                                                    <span class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent-blue px-1 text-[10px] font-bold text-white">{{ count($day['matches']) }}</span>
                                                </a>
                                            @else
                                                <span class="rounded-lg px-1 py-1.5 text-sm {{ $day['isToday'] ? 'border border-accent-green text-accent-green font-bold' : 'text-text-muted' }}">{{ $day['day'] }}</span>
                                            @endif
                                        @else
                                            <span></span>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
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
                          id="match-{{ $match->id }}"
                          class="rounded-xl border border-border-default bg-surface-800 p-5 space-y-4 scroll-mt-24"
                          x-data="{ venueType: 'national', offer: 0, maxBudget: {{ $federationBudget }}, appLocale: @js(\App\Support\LocaleFormat::jsLocale()), fmtEUR(v) { return new Intl.NumberFormat(this.appLocale, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(Number(v)); } }">
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
                                    <option value="{{ $s['key'] }}">
                                        {{ $s['stadium'] }} — {{ $s['club'] ?? $s['owner'] }} ({{ number_format($s['capacity'], 0, ',', '.') }}) · {{ number_format($s['rental_price'], 0, ',', '.') }} €
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

                        {{-- Offer: pay whatever you want, within the federation budget.
                             Only for women's clubs: men's clubs name their own price. --}}
                        <div x-show="venueType === 'club'" x-cloak
                             class="rounded-lg border border-accent-green/30 bg-accent-green/5 p-4">
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-sm font-semibold text-text-body">💰 {{ __('game.venue_org_offer') }}</label>
                                <span class="text-base font-bold text-accent-green" x-text="fmtEUR(offer)"></span>
                            </div>
                            <input type="range" name="offer" x-model.number="offer" min="0" :max="maxBudget" step="100000"
                                   class="w-full accent-green-500">
                            <div class="flex justify-between text-[11px] text-text-muted mt-1">
                                <span x-text="fmtEUR(0)"></span>
                                <span>{{ __('game.venue_org_budget_left', ['amount' => (new \NumberFormatter(\App\Support\LocaleFormat::jsLocale(), \NumberFormatter::DECIMAL))->format($federationBudget)]) }}</span>
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

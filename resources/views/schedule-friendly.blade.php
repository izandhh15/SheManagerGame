<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-32">
        <div class="mt-6 mb-6">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.schedule_friendly_title') }}</h2>
            <p class="text-sm text-text-secondary mt-1">{{ __('game.schedule_friendly_subtitle', ['team' => $userTeam->name]) }}</p>
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

        {{-- Already scheduled friendlies --}}
        @if($scheduled->isNotEmpty())
            <div class="mb-8">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-text-secondary mb-3">{{ __('game.friendly_scheduled_title') }}</h3>
                <div class="space-y-2">
                    @foreach($scheduled as $match)
                        <div class="flex items-center gap-3 rounded-lg border border-border-default bg-surface-800 px-4 py-3">
                            <x-team-crest :team="$match->awayTeam" class="w-8 h-8" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-text-body truncate">{{ $userTeam->name }} vs {{ $match->awayTeam->name }}</p>
                                <p class="text-xs text-text-muted">{{ $match->scheduled_date->format('d/m/Y') }} · {{ $match->neutral_venue_name }}</p>
                                @if($match->venue_status === 'pending_club')
                                    <p class="text-xs text-accent-orange mt-0.5">⏳ {{ __('game.friendly_venue_pending') }}</p>
                                @elseif($match->venue_status === 'rejected')
                                    <p class="text-xs text-accent-red mt-0.5">🚫 {{ __('game.friendly_venue_rejected_badge') }}</p>
                                @endif
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full bg-accent-blue/15 text-accent-blue">{{ __('game.friendly_badge') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- FIFA windows overview --}}
        <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach($windows as $i => $w)
                <div class="rounded-lg border px-4 py-3 {{ $w['remaining'] > 0 ? 'border-border-default bg-surface-800' : 'border-border-default/50 bg-surface-800/50 opacity-60' }}">
                    <p class="text-sm font-semibold text-text-body">{{ $w['label'] }}</p>
                    <p class="text-xs text-text-muted">{{ \Carbon\Carbon::parse($w['start'])->format('d/m') }} – {{ \Carbon\Carbon::parse($w['end'])->format('d/m/Y') }}</p>
                    <p class="text-xs mt-1 {{ $w['remaining'] > 0 ? 'text-accent-green' : 'text-text-muted' }}">
                        {{ __('game.friendly_window_slots', ['used' => $w['scheduled'], 'max' => $maxPerWindow]) }}
                    </p>
                </div>
            @endforeach
        </div>

        {{-- Training camp configurator (national teams) --}}
        <div id="stage-configurator"
             x-data="{ stageFormOpen: {{ $errors->any() ? 'true' : 'false' }} }"
             @close-stage-form.window="stageFormOpen = false"
             class="rounded-xl border border-accent-blue/30 bg-accent-blue/5 p-5 mb-6 scroll-mt-24">
            <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
                <h2 class="text-lg font-bold text-text-primary">🏕️ {{ __('game.stage_config_title') }}</h2>
                <span class="text-sm text-text-secondary">{{ __('game.stage_budget') }}:
                    <strong class="text-text-primary">{{ \App\Support\Money::format((int) ($federationBudget * 100)) }}</strong>
                </span>
            </div>
            <p class="text-sm text-text-secondary mb-4">{{ __('game.stage_config_subtitle') }}</p>

            @if($stageConfig)
                {{-- $stageCost / $stageLines los calcula ShowScheduleFriendly. --}}
                <div class="rounded-lg border border-emerald-600/40 bg-emerald-950/30 p-4">
                    <p class="font-bold text-text-primary mb-1">✅ {{ __('game.stage_organized_title') }}</p>
                    <p class="text-sm text-text-secondary mb-2">{{ __('game.stage_organized_in', ['destination' => $stageConfig['destination']]) }}</p>
                    <ul class="text-sm text-text-secondary space-y-0.5 mb-2">
                        @foreach($stageLines as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <p class="text-sm text-text-secondary">{{ __('game.stage_cost') }}:
                        <strong class="text-text-primary">{{ \App\Support\Money::format((int) ($stageCost * 100)) }}</strong>
                    </p>
                </div>
            @else
                {{-- Visible entry point: opens the stage form without hunting for the URL --}}
                <button type="button"
                        x-show="!stageFormOpen"
                        @click="stageFormOpen = true; $nextTick(() => document.getElementById('stage-form').scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        class="w-full sm:w-auto px-6 py-3 rounded-lg bg-accent-blue text-white font-bold text-sm hover:brightness-110 transition min-h-[44px]">
                    🏕️ {{ __('game.stage_organize_button') }}
                </button>

                <div id="stage-form" x-show="stageFormOpen" x-cloak>
                <form method="POST" action="{{ route('game.schedule-friendly.stage.save', $game->id) }}"
                      x-data="trainingStage({
                          countries: @js($stageCountries),
                          homeCountry: @js($stageHomeCountry),
                          budget: @js($federationBudget),
                          initialDestination: @js($stageHomeCountry),
                          durations: @js($stageDurations),
                          intensities: @js($stageIntensities),
                          focuses: @js($stageFocuses),
                          labels: @js([
                              'fitness' => __('game.stage_effect_fitness', ['value' => '{v}']),
                              'morale' => __('game.stage_effect_morale', ['value' => '{v}']),
                              'injury' => __('game.stage_effect_injury', ['risk' => '{v}']),
                              'youth' => __('game.stage_effect_youth', ['boost' => '{v}']),
                          ]),
                      })">
                    @csrf

                    {{-- Destination --}}
                    <div class="mb-4">
                        <label for="stage_destination" class="block text-sm font-semibold text-text-body mb-2">{{ __('game.stage_destination') }}</label>
                        <select id="stage_destination" name="destination" x-model="destination"
                                @change="$dispatch('stage-destination', destination)" required
                                class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                            <option value="">{{ __('game.friendly_choose_stadium') }}</option>
                            <template x-for="c in countries" :key="c">
                                <option :value="c" x-text="c === homeCountry ? c + ' 🏠' : c"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Duration --}}
                    <div class="mb-4">
                        <p class="block text-sm font-semibold text-text-body mb-2">{{ __('game.stage_duration') }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="cursor-pointer">
                                <input type="radio" name="duration" value="1w" x-model="duration" class="sr-only peer">
                                <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-center text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10 peer-checked:font-semibold">
                                    {{ __('game.stage_duration_1w') }}
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="duration" value="2w" x-model="duration" class="sr-only peer">
                                <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-center text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10 peer-checked:font-semibold">
                                    {{ __('game.stage_duration_2w') }}
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- Intensity --}}
                    <div class="mb-4">
                        <p class="block text-sm font-semibold text-text-body mb-2">{{ __('game.stage_intensity') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            @foreach(['light' => 'stage_intensity_light', 'balanced' => 'stage_intensity_balanced', 'intense' => 'stage_intensity_intense'] as $value => $labelKey)
                                <label class="cursor-pointer">
                                    <input type="radio" name="intensity" value="{{ $value }}" x-model="intensity" class="sr-only peer">
                                    <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10">
                                        <span class="block font-semibold text-center">{{ __("game.{$labelKey}") }}</span>
                                        <span class="block text-xs text-text-muted text-center mt-0.5">{{ __("game.{$labelKey}_desc") }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Focus --}}
                    <div class="mb-4">
                        <p class="block text-sm font-semibold text-text-body mb-2">{{ __('game.stage_focus') }}</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            @foreach(['physical' => 'stage_focus_physical', 'tactical' => 'stage_focus_tactical', 'youth' => 'stage_focus_youth'] as $value => $labelKey)
                                <label class="cursor-pointer">
                                    <input type="radio" name="focus" value="{{ $value }}" x-model="focus" class="sr-only peer">
                                    <span class="block rounded-lg border border-border-default bg-surface-900 px-3 py-2.5 text-sm text-text-body peer-checked:border-accent-blue peer-checked:bg-accent-blue/10">
                                        <span class="block font-semibold text-center">{{ __("game.{$labelKey}") }}</span>
                                        <span class="block text-xs text-text-muted text-center mt-0.5">{{ __("game.{$labelKey}_desc") }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Live summary --}}
                    <div class="rounded-lg border border-border-default bg-surface-900/60 p-4 mb-4">
                        <p class="text-sm font-bold text-text-primary mb-2">📋 {{ __('game.stage_summary_title') }}</p>
                        <ul class="text-sm text-text-secondary space-y-0.5">
                            <template x-for="line in effects" :key="line">
                                <li x-text="line"></li>
                            </template>
                        </ul>
                        <p class="mt-2 text-sm text-text-secondary">{{ __('game.stage_cost') }}:
                            <strong class="text-text-primary" x-text="formatMoney(cost)"></strong>
                        </p>
                        <p x-show="destination && !affordable" x-cloak class="mt-1 text-sm font-semibold text-red-400">
                            ⚠️ {{ __('game.stage_not_enough_budget') }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" :disabled="!affordable"
                                class="w-full sm:w-auto px-6 py-3 rounded-lg bg-accent-blue text-white font-bold text-sm hover:brightness-110 transition disabled:opacity-40 disabled:cursor-not-allowed min-h-[44px]">
                            {{ __('game.stage_confirm') }}
                        </button>
                        <button type="button" @click="$dispatch('close-stage-form')"
                                class="w-full sm:w-auto px-6 py-3 rounded-lg border border-border-default text-sm font-semibold text-text-secondary hover:bg-surface-800 transition min-h-[44px]">
                            {{ __('game.stage_cancel') }}
                        </button>
                    </div>
                </form>
                </div>
            @endif
        </div>

        {{-- Scheduling form --}}
        <form method="POST" action="{{ route('game.schedule-friendly.store', $game->id) }}" class="rounded-xl border border-border-default bg-surface-800 p-5 space-y-5"
              x-data="{ stageCountry: @js($stageConfig['destination'] ?? ''), venueType: 'national' }"
              @stage-destination.window="stageCountry = $event.detail">
            @csrf

            <div>
                <label for="opponent_id" class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_opponent') }}</label>
                <select name="opponent_id" id="opponent_id" required
                        class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                    <option value="">{{ __('game.friendly_choose_opponent') }}</option>
                    <optgroup label="{{ __('game.friendly_national_teams') }}">
                        @foreach($opponents as $opp)
                            <option value="{{ $opp->id }}">{{ $opp->name }}</option>
                        @endforeach
                    </optgroup>
                    @foreach($clubs as $country => $countryClubs)
                        <optgroup label="{{ $country }} — {{ __('game.friendly_clubs') }}"
                                  x-show="!stageCountry || stageCountry === '{{ $country }}'">
                            @foreach($countryClubs as $club)
                                <option value="{{ $club->id }}">{{ $club->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_opponent_hint') }}</p>
            </div>

            <div>
                <label for="date" class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_date') }}</label>
                <input type="date" name="date" id="date" required
                       min="{{ $windows->min('start') }}" max="{{ $windows->max('end') }}"
                       class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50" />
                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_date_hint') }}</p>
            </div>

            {{-- Country filter for stadiums and clubs --}}
            <div class="rounded-lg border border-border-default bg-surface-900/50 p-4">
                <label for="stage_country" class="block text-sm font-semibold text-text-body mb-2">🌍 {{ __('game.friendly_stage_title') }}</label>
                <select id="stage_country" x-model="stageCountry"
                        class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                    <option value="">{{ __('game.friendly_stage_all') }}</option>
                    @foreach($stadiums as $country => $list)
                        <option value="{{ $country }}">{{ $country }} ({{ count($list) }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_stage_hint') }}</p>
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
                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_venue_type_hint') }}</p>
            </div>

            {{-- Own national stadium (always available) --}}
            <div x-show="venueType === 'national'">
                <label for="stadium" class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_stadium') }}</label>
                <select name="stadium" id="stadium"
                        class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                    @foreach($stadiums as $country => $list)
                        <optgroup label="{{ $country }}" x-show="!stageCountry || stageCountry === '{{ $country }}'">
                            @foreach($list as $s)
                                <option value="{{ $s['stadium'] }}" @selected($defaultStadium && $s['stadium'] === $defaultStadium['stadium'])>
                                    {{ $s['stadium'] }} — {{ $s['city'] }} ({{ number_format($s['capacity'], 0, ',', '.') }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_stadium_hint') }}</p>
            </div>

            {{-- Request a women's club home ground (the club decides) --}}
            <div x-show="venueType === 'club'">
                <label for="club_team_id" class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_venue_club_label') }}</label>
                <select name="club_team_id" id="club_team_id"
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
                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_venue_club_hint') }}</p>
            </div>

            {{-- Request a men's big stadium (the men's club decides) --}}
            <div x-show="venueType === 'mens'">
                <label for="mens_stadium" class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_venue_mens_label') }}</label>
                <select name="mens_stadium" id="mens_stadium"
                        class="w-full rounded-lg border border-border-default bg-surface-900 px-4 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                    @foreach($mensStadiums as $s)
                        <option value="{{ $s['key'] }}">
                            {{ $s['stadium'] }} — {{ $s['club'] ?? $s['owner'] }} ({{ number_format($s['capacity'], 0, ',', '.') }}) · {{ number_format($s['rental_price'], 0, ',', '.') }} €
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_venue_mens_hint') }}</p>
            </div>

            {{-- Neutral ground (always available, smaller gate) --}}
            <div x-show="venueType === 'neutral'">
                <div class="rounded-lg border border-border-default bg-surface-900 px-4 py-3 text-sm text-text-body">
                    <p class="font-semibold">{{ $neutralVenueName }} ({{ number_format($neutralVenueCapacity, 0, ',', '.') }})</p>
                    <p class="text-xs text-text-muted mt-1">{{ __('game.friendly_venue_neutral_hint') }}</p>
                </div>
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-accent-blue px-4 py-3 text-sm font-bold uppercase tracking-wide text-white hover:brightness-110 transition">
                {{ __('game.friendly_submit') }}
            </button>
        </form>

        <div class="mt-6">
            <a href="{{ route('show-game', $game->id) }}" class="text-sm text-accent-blue underline">← {{ __('game.back_to_dashboard') }}</a>
        </div>
    </div>
</x-app-layout>

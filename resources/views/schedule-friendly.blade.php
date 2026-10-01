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

        {{-- Scheduling form --}}
        <form method="POST" action="{{ route('game.schedule-friendly.store', $game->id) }}" class="rounded-xl border border-border-default bg-surface-800 p-5 space-y-5"
              x-data="{ stageCountry: '' }">
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

            {{-- Training camp (stage): pick a country to filter its stadiums --}}
            <div class="rounded-lg border border-accent-blue/30 bg-accent-blue/5 p-4">
                <label for="stage_country" class="block text-sm font-semibold text-text-body mb-2">🏕️ {{ __('game.friendly_stage_title') }}</label>
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
                <label for="stadium" class="block text-sm font-semibold text-text-body mb-2">{{ __('game.friendly_stadium') }}</label>
                <select name="stadium" id="stadium" required
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

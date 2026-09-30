{{-- Shared club grid used by career mode (step: pick club) and dual mode
     (step 1: pick club). $countries / $leagues come from
     App\Http\Views\SelectTeam; everything Alpine-bound (mode, openTab)
     lives in the select-team page's top-level x-data scope.

     Variables (all optional):
       $radioName  - name attribute of the team radio (default 'team_id')
       $alpineMode - mode value gating required/disabled (default 'career')
       $xModel     - when set, radios bind with x-model instead of the
                    required/disabled pair (used by dual mode's clubId)
--}}
@php
    $radioName ??= 'team_id';
    $alpineMode ??= 'career';
    $xModel ??= null;
@endphp

{{-- League picker. ESP3A/ESP3B share one 'ESP3' entry, built in
     App\Http\Views\SelectTeam so its labels can be translated in PHP. --}}
<x-league-select model="openTab" options="leagues" :label="__('game.league')" />

{{-- Team grids per competition. ESP3A/ESP3B share the 'ESP3' tab and render as group sections. --}}
@foreach($countries as $countryCode => $country)
    @foreach($country['tiers'] as $tier => $competition)
        @php
            $isPrimeraRfef = in_array($competition->id, ['ESP3A', 'ESP3B'], true);
            $activeTabId = $isPrimeraRfef ? 'ESP3' : $competition->id;
            $groupHeadingKey = match ($competition->id) {
                'ESP3A' => 'game.group_1',
                'ESP3B' => 'game.group_2',
                default => null,
            };
        @endphp
        <div x-show="openTab === '{{ $activeTabId }}'" x-cloak @class(['mb-4' => $isPrimeraRfef])>
            @if($groupHeadingKey)
                <h3 class="font-heading text-sm md:text-base font-semibold uppercase tracking-wide text-text-secondary mb-2">{{ __($groupHeadingKey) }}</h3>
            @endif
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
                @foreach($competition->teams as $team)
                    @if($team->isReserveTeam())
                        <div x-data x-tooltip.raw="{{ __('game.b_team_not_playable') }}"
                             class="flex items-center gap-2 md:gap-3 rounded-lg border border-border-default p-2 md:p-4 opacity-60 cursor-not-allowed">
                            <x-team-crest :team="$team" class="w-7 h-7 md:w-10 md:h-10 shrink-0" />
                            <span class="text-xs md:text-base font-medium text-text-muted truncate">{{ $team->name }}</span>
                        </div>
                    @else
                        <label class="flex items-center gap-2 md:gap-3 rounded-lg border border-border-default p-2 md:p-4 cursor-pointer transition-all
                                       hover:bg-accent-blue/5 hover:border-accent-blue/30
                                       has-checked:ring-2 has-checked:ring-accent-blue has-checked:border-accent-blue/30 has-checked:bg-accent-blue/5">
                            <x-team-crest :team="$team" class="w-7 h-7 md:w-10 md:h-10 shrink-0" />
                            <span class="text-xs md:text-base font-medium text-text-body truncate">{{ $team->name }}</span>
                            @if($xModel)
                                <input x-model="{{ $xModel }}" type="radio" name="{{ $radioName }}" value="{{ $team->id }}" class="hidden">
                            @else
                                <input x-bind:required="mode === '{{ $alpineMode }}'" x-bind:disabled="mode !== '{{ $alpineMode }}'" type="radio" name="{{ $radioName }}" value="{{ $team->id }}" class="hidden">
                            @endif
                        </label>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
@endforeach

<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-32">
        <div class="mt-6 mb-6 flex items-center gap-4">
            <x-team-crest :team="$team" class="w-14 h-14 md:w-16 md:h-16" />
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.squad_picker_title') }}: {{ $team->name }}</h2>
                <p class="text-sm text-text-secondary mt-1">{{ __('game.squad_picker_subtitle') }}</p>
                @if($window ?? null)
                    <p class="text-xs text-text-muted mt-1">{{ __('game.squad_picker_window_note', ['start' => \Carbon\Carbon::parse($window['start'])->format('d/m/Y'), 'end' => \Carbon\Carbon::parse($window['end'])->format('d/m/Y')]) }}</p>
                @endif
            </div>
        </div>

        {{-- Dual mode: remind that this call-up joins the already-picked club,
             and that the two saves are separate simulations. --}}
        @if($dualClub ?? null)
        <div class="mb-6 rounded-lg border border-accent-green/30 bg-accent-green/5 px-4 py-2.5">
            <p class="text-sm text-text-body">
                <span class="font-semibold text-accent-green">{{ __('game.mode_dual') }}:</span>
                {{ $dualClub->name }} + {{ $team->name }}
            </p>
            <p class="text-xs text-text-muted mt-1">{{ __('game.dual_picker_note') }}</p>
        </div>
        @endif

        @php
            // Payload for client-side filtering/sorting (Alpine). Includes the
            // injured badge label so the list can be fully rendered in JS.
            $playersPayload = $players->map(fn ($p) => [
                'player_id' => $p['player_id'],
                'name' => $p['name'],
                'position' => $p['position'],
                'group' => $p['group'],
                'overall' => $p['overall'],
                'age' => $p['age'],
                'club' => $p['club'],
                'club_form' => $p['club_form'],
                'injured_label' => isset($injured[$p['player_id']])
                    ? __('game.squad_picker_injured_until', ['date' => \Carbon\Carbon::parse($injured[$p['player_id']])->format('d/m/Y')])
                    : null,
                'season_apps' => $clubStats[$p['player_id']]['apps'] ?? null,
                'season_goals' => $clubStats[$p['player_id']]['goals'] ?? null,
                'season_assists' => $clubStats[$p['player_id']]['assists'] ?? null,
                'has_real_club_stats' => isset($clubStats[$p['player_id']]),
                'caps' => $nationalStats[$p['player_id']]['caps'] ?? null,
                'nt_goals' => $nationalStats[$p['player_id']]['goals'] ?? null,
            ])->values();
            $groupLabels = [
                'Goalkeeper' => __('squad.goalkeepers'),
                'Defender' => __('squad.defenders'),
                'Midfielder' => __('squad.midfielders'),
                'Forward' => __('squad.forwards'),
            ];
            $groupShort = [
                'Goalkeeper' => __('squad.goalkeepers_short'),
                'Defender' => __('squad.defenders_short'),
                'Midfielder' => __('squad.midfielders_short'),
                'Forward' => __('squad.forwards_short'),
            ];
            $order = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
            $positionGroups = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
        @endphp

        {{-- Payload for client-side filtering/sorting (Alpine). It lives in a
             JSON script block, NOT inside the x-data attribute: @json output
             contains literal double quotes (JSON syntax) that would terminate
             the attribute early and dump the JS as page text. --}}
        <script type="application/json" id="squad-picker-data">@json(['players' => $playersPayload, 'groupLabels' => $groupLabels, 'yearsLabel' => __('app.years')])</script>

        <div x-data="{
                q: '',
                clubFilter: '',
                sortBy: 'position',
                posFilter: { Goalkeeper: true, Defender: true, Midfielder: true, Forward: true },
                players: JSON.parse(document.getElementById('squad-picker-data').textContent).players,
                groupLabels: JSON.parse(document.getElementById('squad-picker-data').textContent).groupLabels,
                yearsLabel: JSON.parse(document.getElementById('squad-picker-data').textContent).yearsLabel,
                selected: [],
                toggle(id) {
                    const i = this.selected.indexOf(id);
                    if (i >= 0) { this.selected.splice(i, 1); }
                    else if (this.selected.length < 23) { this.selected.push(id); }
                },
                isSelected(id) { return this.selected.includes(id); },
                visiblePlayers() {
                    const groupOrder = { Goalkeeper: 0, Defender: 1, Midfielder: 2, Forward: 3 };
                    const ql = this.q.toLowerCase();
                    let list = this.players.filter(p =>
                        (this.q === '' || p.name.toLowerCase().includes(ql)) &&
                        (this.clubFilter === '' || (p.club || '') === this.clubFilter) &&
                        this.posFilter[p.group]
                    );
                    if (this.sortBy === 'overall_desc') {
                        list.sort((a, b) => b.overall - a.overall || a.name.localeCompare(b.name));
                    } else if (this.sortBy === 'overall_asc') {
                        list.sort((a, b) => a.overall - b.overall || a.name.localeCompare(b.name));
                    } else {
                        list.sort((a, b) => (groupOrder[a.group] ?? 99) - (groupOrder[b.group] ?? 99) || b.overall - a.overall || a.name.localeCompare(b.name));
                    }
                    return list;
                },
                playersInGroup(group) {
                    return this.visiblePlayers().filter(p => p.group === group);
                },
            }">
            <div class="sticky top-0 z-10 bg-surface-900/95 backdrop-blur py-3 space-y-3">
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" x-model="q" placeholder="{{ __('game.squad_picker_search') }}"
                           class="flex-1 rounded-lg border border-border-default bg-surface-800 px-4 py-2.5 text-sm text-text-body placeholder:text-text-muted focus:outline-none focus:ring-2 focus:ring-accent-blue/50" />
                    <select x-model="clubFilter"
                            class="sm:w-56 rounded-lg border border-border-default bg-surface-800 px-3 py-2.5 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                        <option value="">{{ __('game.squad_picker_all_clubs') }}</option>
                        @foreach($clubs as $club)
                            <option value="{{ $club }}">{{ $club }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-5">
                    <label class="flex items-center gap-2 text-sm text-text-secondary">
                        <span>{{ __('game.squad_picker_sort') }}:</span>
                        <select x-model="sortBy"
                                class="rounded-lg border border-border-default bg-surface-800 px-3 py-2 text-sm text-text-body focus:outline-none focus:ring-2 focus:ring-accent-blue/50">
                            <option value="position">{{ __('game.squad_picker_sort_position') }}</option>
                            <option value="overall_desc">{{ __('game.squad_picker_sort_overall_desc') }}</option>
                            <option value="overall_asc">{{ __('game.squad_picker_sort_overall_asc') }}</option>
                        </select>
                    </label>
                    <div class="flex items-center gap-3 text-sm">
                        <span class="text-text-secondary">{{ __('game.squad_picker_positions') }}:</span>
                        @foreach($positionGroups as $g)
                            <label class="inline-flex items-center gap-1.5 cursor-pointer text-text-body select-none">
                                <input type="checkbox" x-model="posFilter.{{ $g }}" class="w-4 h-4 rounded" />
                                <span class="font-semibold">{{ $groupShort[$g] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold" :class="selected.length === 23 ? 'text-accent-green' : 'text-text-secondary'">
                        <span x-text="selected.length"></span> / 23
                    </p>
                    <button type="button" @click="selected = []" x-show="selected.length > 0" class="text-xs text-text-muted underline">
                        {{ __('app.clear') }}
                    </button>
                </div>
            </div>

            @if($updateGame ?? null)
            {{-- Update mode: re-pick the 23 for an existing game (per-break convocatoria). --}}
            <form method="post" action="{{ route('game.national-squad.update', $updateGame->id) }}" @submit="if (selected.length !== 23) $event.preventDefault()">
                @csrf
            @elseif($dualClub ?? null)
            {{-- Dual mode: club + nation + 23 in one POST to the dual endpoint. --}}
            <form method="post" action="{{ route('init-dual-game') }}" @submit="if (selected.length !== 23) $event.preventDefault()">
                @csrf
                <input type="hidden" name="club_id" value="{{ $dualClub->id }}" />
                <input type="hidden" name="national_team_id" value="{{ $team->id }}" />
            @else
            <form method="post" action="{{ route('init-national-game') }}" @submit="if (selected.length !== 23) $event.preventDefault()">
                @csrf
                <input type="hidden" name="team_id" value="{{ $team->id }}" />
            @endif
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="player_ids[]" :value="id" />
                </template>

                {{-- Flat list (sorted by media). Each x-for template has a SINGLE
                     root element: Alpine's x-for only clones firstElementChild. --}}
                <div x-show="sortBy !== 'position'" class="space-y-1.5 mt-2">
                    <template x-for="p in visiblePlayers()" :key="p.player_id">
                        @include('partials.squad-picker-card')
                    </template>
                </div>

                {{-- Grouped by position. Headers are server-rendered; each group
                     gets its own x-for with a single root element. --}}
                <div x-show="sortBy === 'position'">
                    @foreach($order as $pos)
                    <div x-show="playersInGroup('{{ $pos }}').length > 0">
                        <h3 class="font-heading text-sm md:text-base font-semibold uppercase tracking-wide text-text-secondary mt-6 mb-2">{{ $groupLabels[$pos] ?? $pos }}</h3>
                        <div class="space-y-1.5 mt-2">
                            <template x-for="p in playersInGroup('{{ $pos }}')" :key="p.player_id">
                                @include('partials.squad-picker-card')
                            </template>
                        </div>
                    </div>
                    @endforeach
                </div>

                <p x-show="visiblePlayers().length === 0" class="text-sm text-text-muted text-center py-8">{{ __('game.squad_picker_no_results') }}</p>
                <x-input-error :messages="$errors->get('player_ids')" class="mt-4" />
                <x-input-error :messages="$errors->get('club_id')" class="mt-4" />
                <x-input-error :messages="$errors->get('national_team_id')" class="mt-4" />

                {{-- Sticky confirm bar --}}
                <div class="fixed bottom-0 inset-x-0 z-20 bg-surface-900/95 backdrop-blur border-t border-border-strong">
                    <div class="max-w-4xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
                        <p class="text-sm text-text-secondary">
                            <span class="font-bold text-text-body" x-text="selected.length"></span> / 23
                        </p>
                        <button type="submit"
                                :disabled="selected.length !== 23"
                                :class="selected.length === 23 ? 'bg-accent-blue hover:bg-accent-blue/90' : 'bg-surface-600 cursor-not-allowed opacity-60'"
                                class="px-6 py-2.5 min-h-[44px] rounded-lg text-sm font-semibold text-white transition">
                            {{ ($dualClub ?? null) ? __('game.dual_confirm') : __('game.squad_picker_confirm') }}
                        </button>
                    </div>
                </div>
            </form>

            <p x-show="selected.length !== 23" class="text-xs text-text-muted mt-3 text-center">{{ __('game.squad_picker_need_23') }}</p>
        </div>
    </div>
</x-app-layout>

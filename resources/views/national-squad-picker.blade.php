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
                'injured_label' => isset($injured[$p['player_id']])
                    ? __('game.squad_picker_injured_until', ['date' => \Carbon\Carbon::parse($injured[$p['player_id']])->format('d/m/Y')])
                    : null,
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
            $positionGroups = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
        @endphp

        <div x-data="{
                q: '',
                clubFilter: '',
                sortBy: 'position',
                posFilter: { Goalkeeper: true, Defender: true, Midfielder: true, Forward: true },
                players: @json($playersPayload),
                groupLabels: @json($groupLabels),
                yearsLabel: @json(__('app.years')),
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
                    if (this.sortBy !== 'position') return list;
                    const out = [];
                    let lastGroup = null;
                    for (const p of list) {
                        if (p.group !== lastGroup) { out.push({ header: p.group }); lastGroup = p.group; }
                        out.push(p);
                    }
                    return out;
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

                <div class="space-y-1.5 mt-2">
                    <template x-for="p in visiblePlayers()" :key="p.header ? 'header-' + p.header : p.player_id">
                        <template x-if="p.header">
                            <h3 class="font-heading text-sm md:text-base font-semibold uppercase tracking-wide text-text-secondary mt-6 mb-2" x-text="groupLabels[p.header]"></h3>
                        </template>
                        <template x-if="!p.header">
                            <div @click="if (!p.injured_label) toggle(p.player_id)"
                                 :class="[isSelected(p.player_id) ? 'border-accent-blue/60 bg-accent-blue/10' : 'border-border-default hover:bg-surface-700/50', p.injured_label ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer']"
                                 class="flex items-center gap-3 rounded-lg border p-2.5 md:p-3 transition-all select-none">
                                <div class="shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center"
                                     :class="isSelected(p.player_id) ? 'border-accent-blue bg-accent-blue' : 'border-border-strong'">
                                    <svg x-show="isSelected(p.player_id)" class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm md:text-base font-medium text-text-body truncate" x-text="p.name"></p>
                                    <p class="text-xs text-text-muted truncate"><span x-text="p.position"></span><template x-if="p.club"><span> · <span x-text="p.club"></span></span></template></p>
                                    <template x-if="p.injured_label">
                                        <p class="text-[11px] font-semibold text-red-400 mt-0.5" x-text="p.injured_label"></p>
                                    </template>
                                </div>
                                <div class="shrink-0 text-right">
                                    <span class="inline-block min-w-10 text-center text-sm font-bold px-2 py-1 rounded bg-surface-700 text-text-body" x-text="p.overall"></span>
                                    <template x-if="p.age"><p class="text-[11px] text-text-muted mt-0.5"><span x-text="p.age"></span> <span x-text="yearsLabel"></span></p></template>
                                </div>
                            </div>
                        </template>
                    </template>
                    <template x-if="visiblePlayers().length === 0">
                        <p class="text-sm text-text-muted text-center py-8">{{ __('game.squad_picker_no_results') }}</p>
                    </template>
                </div>

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

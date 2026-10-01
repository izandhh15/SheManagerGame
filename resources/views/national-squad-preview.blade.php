<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-16">
        <div class="mt-6 mb-6 flex items-center gap-4">
            <x-team-crest :team="$team" class="w-14 h-14 md:w-16 md:h-16" />
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.squad_preview_title') }}: {{ $team->name }}</h2>
                <p class="text-sm text-text-secondary mt-1">{{ __('game.squad_preview_subtitle', ['count' => $players->count()]) }}</p>
            </div>
        </div>

        <a href="{{ route('select-team') }}" class="inline-flex items-center gap-1.5 text-sm text-accent-blue hover:underline mb-6">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
            {{ __('game.back_to_select_team') }}
        </a>

        @php
            $playersPayload = $players->map(fn ($p) => [
                'name' => $p['name'],
                'position' => $p['position'],
                'group' => $p['group'],
                'overall' => $p['overall'],
                'age' => $p['age'],
                'club' => $p['club'],
            ])->values();
            $groupLabels = [
                'Goalkeeper' => __('squad.goalkeepers'),
                'Defender' => __('squad.defenders'),
                'Midfielder' => __('squad.midfielders'),
                'Forward' => __('squad.forwards'),
            ];
            $order = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
        @endphp

        {{-- Payload for client-side filtering (Alpine). Lives in a JSON script
             block, NOT inside the x-data attribute: @json output contains
             literal double quotes that would terminate the attribute early. --}}
        <script type="application/json" id="squad-preview-data">@json(['players' => $playersPayload, 'groupLabels' => $groupLabels, 'yearsLabel' => __('app.years')])</script>

        <div x-data="{
                q: '',
                clubFilter: '',
                players: JSON.parse(document.getElementById('squad-preview-data').textContent).players,
                groupLabels: JSON.parse(document.getElementById('squad-preview-data').textContent).groupLabels,
                yearsLabel: JSON.parse(document.getElementById('squad-preview-data').textContent).yearsLabel,
                visiblePlayers() {
                    const groupOrder = { Goalkeeper: 0, Defender: 1, Midfielder: 2, Forward: 3 };
                    const ql = this.q.toLowerCase();
                    let list = this.players.filter(p =>
                        (this.q === '' || p.name.toLowerCase().includes(ql)) &&
                        (this.clubFilter === '' || (p.club || '') === this.clubFilter)
                    );
                    list.sort((a, b) => (groupOrder[a.group] ?? 99) - (groupOrder[b.group] ?? 99) || b.overall - a.overall || a.name.localeCompare(b.name));
                    return list;
                },
                playersInGroup(group) {
                    return this.visiblePlayers().filter(p => p.group === group);
                },
            }">
            <div class="sticky top-0 z-10 bg-surface-900/95 backdrop-blur py-3">
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
            </div>

            {{-- Grouped by position. Headers are server-rendered; each group
                 gets its own x-for with a single root element. --}}
            @foreach($order as $pos)
            <div x-show="playersInGroup('{{ $pos }}').length > 0">
                <h3 class="font-heading text-sm md:text-base font-semibold uppercase tracking-wide text-text-secondary mt-6 mb-2">
                    {{ $groupLabels[$pos] ?? $pos }}
                    <span class="text-text-muted font-sans font-normal" x-text="'(' + playersInGroup('{{ $pos }}').length + ')'"></span>
                </h3>
                <div class="space-y-1.5 mt-2">
                    <template x-for="p in playersInGroup('{{ $pos }}')" :key="p.name">
                        <div class="flex items-center gap-3 rounded-lg border border-border-default p-2.5 md:p-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm md:text-base font-medium text-text-body truncate" x-text="p.name"></p>
                                <p class="text-xs text-text-muted truncate"><span x-text="p.position"></span><span x-show="p.club"> · <span x-text="p.club"></span></span></p>
                            </div>
                            <div class="shrink-0 text-right">
                                <span class="inline-block min-w-10 text-center text-sm font-bold px-2 py-1 rounded bg-surface-700 text-text-body" x-text="p.overall"></span>
                                <p x-show="p.age" class="text-[11px] text-text-muted mt-0.5"><span x-text="p.age"></span> <span x-text="yearsLabel"></span></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            @endforeach

            <p x-show="visiblePlayers().length === 0" class="text-sm text-text-muted text-center py-8">{{ __('game.squad_picker_no_results') }}</p>
        </div>
    </div>
</x-app-layout>

<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 pb-32">
        <div class="mt-6 mb-6 flex items-center gap-4">
            <x-team-crest :team="$team" class="w-14 h-14 md:w-16 md:h-16" />
            <div>
                <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.squad_picker_title') }}: {{ $team->name }}</h2>
                <p class="text-sm text-text-secondary mt-1">{{ __('game.squad_picker_subtitle') }}</p>
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

        <div x-data="{
                q: '',
                clubFilter: '',
                selected: [],
                toggle(id) {
                    const i = this.selected.indexOf(id);
                    if (i >= 0) { this.selected.splice(i, 1); }
                    else if (this.selected.length < 23) { this.selected.push(id); }
                },
                isSelected(id) { return this.selected.includes(id); },
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
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold" :class="selected.length === 23 ? 'text-accent-green' : 'text-text-secondary'">
                        <span x-text="selected.length"></span> / 23
                    </p>
                    <button type="button" @click="selected = []" x-show="selected.length > 0" class="text-xs text-text-muted underline">
                        {{ __('app.clear') }}
                    </button>
                </div>
            </div>

            @php
                $groupLabels = ['Goalkeeper' => __('squad.goalkeepers'), 'Defender' => __('squad.defenders'), 'Midfielder' => __('squad.midfielders'), 'Forward' => __('squad.forwards')];
                $grouped = $players->groupBy('group');
                $order = ['Goalkeeper', 'Defender', 'Midfielder', 'Forward'];
            @endphp

            @if($dualClub ?? null)
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

                @foreach($order as $pos)
                    @if($grouped->has($pos))
                        <h3 class="font-heading text-sm md:text-base font-semibold uppercase tracking-wide text-text-secondary mt-6 mb-2">{{ $groupLabels[$pos] ?? $pos }}</h3>
                        <div class="space-y-1.5">
                            @foreach($grouped[$pos] as $p)
                                <div x-show="(q === '' || '{{ addslashes($p['name']) }}'.toLowerCase().includes(q.toLowerCase())) && (clubFilter === '' || clubFilter === '{{ addslashes($p['club'] ?? '') }}')"
                                     @click="toggle('{{ $p['player_id'] }}')"
                                     :class="isSelected('{{ $p['player_id'] }}') ? 'border-accent-blue/60 bg-accent-blue/10' : 'border-border-default hover:bg-surface-700/50'"
                                     class="flex items-center gap-3 rounded-lg border p-2.5 md:p-3 cursor-pointer transition-all select-none">
                                    <div class="shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center"
                                         :class="isSelected('{{ $p['player_id'] }}') ? 'border-accent-blue bg-accent-blue' : 'border-border-strong'">
                                        <svg x-show="isSelected('{{ $p['player_id'] }}')" class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm md:text-base font-medium text-text-body truncate">{{ $p['name'] }}</p>
                                        <p class="text-xs text-text-muted truncate">
                                            {{ $p['position'] }}@if($p['club']) · {{ $p['club'] }}@endif
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <span class="inline-block min-w-10 text-center text-sm font-bold px-2 py-1 rounded bg-surface-700 text-text-body">{{ $p['overall'] }}</span>
                                        @if($p['age'])<p class="text-[11px] text-text-muted mt-0.5">{{ $p['age'] }} {{ __('app.years') }}</p>@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach

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

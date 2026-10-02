{{-- Player card for the national squad picker. Rendered inside an Alpine
     x-for loop, so it must have a SINGLE root element (Alpine's x-for only
     clones firstElementChild of the template). Conditionals use x-show on
     real elements, never nested <template x-if>. Expects `p` in scope. --}}
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
        <p class="text-xs text-text-muted truncate"><span x-text="p.position"></span><span x-show="p.club"> · <span x-text="p.club"></span></span></p>
        {{-- Real club season stats (dual mode) — preferred over synthesized form --}}
        <p x-show="p.has_real_club_stats" class="text-[11px] text-text-secondary mt-0.5 truncate">
            <span x-text="p.season_apps"></span> {{ __('game.squad_picker_apps_short') }} · <span x-text="p.season_goals"></span>{{ __('game.squad_picker_goals_short') }} · <span x-text="p.season_assists"></span>{{ __('game.squad_picker_assists_short') }}
            <span x-show="p.caps" class="text-text-muted">| 🇪🇸 <span x-text="p.caps"></span> {{ __('game.squad_picker_caps_short') }} · <span x-text="p.nt_goals"></span>{{ __('game.squad_picker_goals_short') }}</span>
        </p>
        <p x-show="!p.has_real_club_stats && p.club_form" class="text-[11px] text-text-secondary mt-0.5 truncate">
            <span x-text="p.club_form.minutes"></span>' · <span x-text="p.club_form.goals"></span>{{ __('game.squad_picker_goals_short') }} · <span x-text="p.club_form.assists"></span>{{ __('game.squad_picker_assists_short') }} <span class="text-text-muted">(<span x-text="p.club_form.rating"></span>)</span>
            <span x-show="p.caps" class="text-text-muted">| 🇪🇸 <span x-text="p.caps"></span> {{ __('game.squad_picker_caps_short') }} · <span x-text="p.nt_goals"></span>{{ __('game.squad_picker_goals_short') }}</span>
        </p>
        <p x-show="p.injured_label" class="text-[11px] font-semibold text-red-400 mt-0.5" x-text="p.injured_label"></p>
    </div>
    <div class="shrink-0 text-right">
        <span class="inline-block min-w-10 text-center text-sm font-bold px-2 py-1 rounded bg-surface-700 text-text-body" x-text="p.overall"></span>
        <p x-show="p.age" class="text-[11px] text-text-muted mt-0.5"><span x-text="p.age"></span> <span x-text="yearsLabel"></span></p>
    </div>
</div>

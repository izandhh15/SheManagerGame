@php
/** @var App\Models\Game $game */
/** @var \Illuminate\Support\Collection $jobs */
$jobResult = session('job_result');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">{{ __('game.job_market_title') }}</h2>
            <p class="mt-2 text-sm text-text-secondary">{{ __('game.job_market_intro') }}</p>
            <p class="mt-1 text-xs text-amber-400/90">{{ __('game.job_market_warning') }}</p>
        </div>

        @if($jobResult)
            <div class="mb-6">
                @if($jobResult['fired'])
                    <x-status-banner color="red"
                        :title="__('game.job_betrayal_title')"
                        :description="__('game.job_betrayal_desc', ['team' => $jobResult['team_name']])">
                        <x-slot:icon>
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                        </x-slot:icon>
                    </x-status-banner>
                @elseif($jobResult['accepted'])
                    <x-status-banner color="green"
                        :title="__('game.job_accepted_title')"
                        :description="__($jobResult['discovered'] ? 'game.job_accepted_discovered_desc' : 'game.job_accepted_desc', ['team' => $jobResult['team_name']])">
                        <x-slot:icon>
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </x-slot:icon>
                    </x-status-banner>
                @else
                    <x-status-banner color="blue"
                        :title="__('game.job_rejected_title')"
                        :description="__('game.job_rejected_desc', ['team' => $jobResult['team_name']])">
                        <x-slot:icon>
                            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75h.008v.008H12v-.008Z" />
                            </svg>
                        </x-slot:icon>
                    </x-status-banner>
                @endif
            </div>
        @endif

        <x-section-card :title="__('game.job_market_available')">
            <div class="p-5">
                @if($jobs->isEmpty())
                    <p class="text-sm text-text-muted text-center py-6">{{ __('game.job_market_empty') }}</p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($jobs as $team)
                            <div class="rounded-xl border border-border-default p-4 hover:border-accent-blue/40 transition-colors">
                                <div class="flex items-center gap-3 mb-3">
                                    <x-team-crest :team="$team" class="w-10 h-10 shrink-0" />
                                    <div class="min-w-0">
                                        <div class="font-semibold text-text-primary truncate">{{ $team->name }}</div>
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('game.job-market.apply', ['gameId' => $game->id, 'teamId' => $team->id]) }}" x-data="{ loading: false }" @submit="loading = true">
                                    @csrf
                                    <x-primary-button-spin class="w-full justify-center">
                                        {{ __('game.job_apply') }}
                                    </x-primary-button-spin>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-section-card>

        <div class="mt-4 text-center">
            <a href="{{ route('game.manager.career', $game->id) }}" class="text-sm text-text-muted hover:text-text-primary transition-colors">
                ← {{ __('manager.career_title') }}
            </a>
        </div>
    </div>
</x-app-layout>

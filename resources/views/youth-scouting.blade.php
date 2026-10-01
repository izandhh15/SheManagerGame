@php /** @var App\Models\Game $game **/ @endphp

<x-app-layout>
    <x-slot name="header">
        <x-game-header :game="$game" :next-match="$game->next_match"></x-game-header>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 pb-8">
        <div class="mt-6 mb-4">
            <h2 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
                🔍 {{ app()->getLocale() === 'es' ? 'Ojeador de canteras' : 'Academy scout' }}
            </h2>
            <p class="text-sm text-text-secondary mt-1">
                {{ app()->getLocale() === 'es'
                    ? 'Tus ojeadores han localizado estas perlas en canteras rivales. Puedes intentar "robarlas" pagando una compensación.'
                    : 'Your scouts found these wonderkids in rival academies. You can try to "steal" them for a compensation fee.' }}
            </p>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-500/10 border border-green-500/30 text-sm text-green-600 font-semibold">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-sm text-red-600 font-semibold">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($prospects as $prospect)
                <div class="p-4 rounded-xl bg-surface-800 border border-border-default">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div>
                            <div class="font-bold text-text-primary">{{ $prospect->name }}</div>
                            <div class="text-xs text-text-faint">{{ $prospect->team?->name }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-2xl font-bold {{ $prospect->potential >= 80 ? 'text-green-500' : ($prospect->potential >= 70 ? 'text-yellow-500' : 'text-text-secondary') }}">
                                {{ $prospect->potential }}
                            </div>
                            <div class="text-[10px] uppercase text-text-faint">pot.</div>
                        </div>
                    </div>

                    <div class="flex gap-3 text-xs text-text-secondary mb-3">
                        <span>{{ $prospect->position }}</span>
                        <span>{{ $prospect->age }} {{ app()->getLocale() === 'es' ? 'años' : 'yrs' }}</span>
                        <span>{{ $prospect->overall_score }} {{ app()->getLocale() === 'es' ? 'media' : 'ovr' }}</span>
                    </div>

                    <div class="mb-3">
                        <div class="flex justify-between text-[10px] text-text-faint mb-1">
                            <span>Potencial</span>
                            <span>{{ $prospect->potential_low }} - {{ $prospect->potential_high }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-700 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-yellow-500 to-green-500"
                                 style="width: {{ min(100, $prospect->potential) }}%"></div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('game.scouting.youth.poach', [$game->id, $prospect->id]) }}">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm font-bold uppercase tracking-wide transition-colors">
                            🎯 {{ app()->getLocale() === 'es' ? 'Intentar fichaje' : 'Try to sign' }}
                        </button>
                    </form>
                </div>
            @empty
                <div class="col-span-full p-8 text-center rounded-xl bg-surface-800 border border-border-default">
                    <p class="text-4xl mb-2">🔍</p>
                    <p class="text-text-secondary">
                        {{ app()->getLocale() === 'es'
                            ? 'Tus ojeadores no han encontrado perlas en canteras rivales todavía.'
                            : 'Your scouts haven\'t found wonderkids in rival academies yet.' }}
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>

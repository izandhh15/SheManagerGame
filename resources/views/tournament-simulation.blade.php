{{--
    Tournament simulation driver (tournament mode, user eliminated).

    The `game.simulate-tournament` endpoint is POST-only and chunked (each
    request advances ~20s of simulation, then the client re-POSTs until
    done) — a plain GET redirect can no longer reach it. This view renders
    the loading screen and drives the chunks itself per the endpoint's
    caller contract: POST with CSRF, poll until done === true, then
    navigate to data.redirect.
--}}
@php
/** @var \App\Models\Game $game */
@endphp

<x-app-layout :hide-footer="true">
    <div class="min-h-screen flex items-center justify-center py-8" x-data="tournamentSimulation()" x-init="start()">
        <div class="text-center px-4">
            {{-- Spinner --}}
            <div class="flex justify-center mb-6">
                <svg class="animate-spin h-8 w-8 text-accent-blue" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            {{-- Title --}}
            <h1 class="text-2xl font-bold text-text-primary mb-2">{{ __('game.tournament_simulating') }}</h1>

            {{-- Description --}}
            <p class="text-text-secondary max-w-md mx-auto">{{ __('game.simulating_other_matches_message') }}</p>
        </div>
    </div>

    <script>
        function tournamentSimulation() {
            return {
                async start() {
                    const url = '{{ route('game.simulate-tournament', $game->id) }}';
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

                    while (true) {
                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                },
                            });
                            const data = await response.json();
                            if (data.done) {
                                window.location = data.redirect;
                                return;
                            }
                            // Brief pause between chunks to let the DB settle.
                            await new Promise((r) => setTimeout(r, 800));
                        } catch (e) {
                            // On network error, wait a bit and retry.
                            await new Promise((r) => setTimeout(r, 5000));
                        }
                    }
                }
            };
        }
    </script>
</x-app-layout>

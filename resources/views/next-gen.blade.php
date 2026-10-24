<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SheManagerGame — Nueva generación en camino</title>
    <meta name="description" content="SheManagerGame se está reconstruyendo: nuevos menús, tuits libres de 200 caracteres, ruedas de prensa con respuesta libre y reacción de la prensa.">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-surface-900 text-text-primary">
<div class="min-h-screen flex flex-col overflow-x-hidden">
    <header class="sticky top-0 z-50 bg-surface-900/90 backdrop-blur-md border-b border-border-default">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <span class="w-9 h-9 rounded-xl bg-purple-600 flex items-center justify-center shadow-lg shadow-purple-600/30 text-white font-bold">S</span>
                <span class="font-heading font-bold text-xl uppercase tracking-wide">SheManager<span class="text-purple-400">Game</span></span>
            </a>
            <nav class="flex items-center gap-2 sm:gap-3">
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg text-sm font-medium text-text-secondary hover:text-text-primary">Entrar</a>
                <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg text-sm font-semibold bg-purple-600 text-white hover:bg-purple-500">Jugar</a>
            </nav>
        </div>
    </header>

    <main class="flex-1">
        <section class="relative">
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute -top-32 left-1/2 -translate-x-1/2 w-[900px] h-[500px] bg-purple-600/20 blur-[140px] rounded-full"></div>
            </div>
            <div class="relative max-w-7xl mx-auto px-4 pt-14 sm:pt-20 pb-12 text-center">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-xs sm:text-sm font-semibold uppercase tracking-widest">🚧 En construcción · Nueva generación</span>
                <h1 class="font-heading font-extrabold uppercase tracking-tight text-4xl sm:text-6xl mt-6 leading-[0.95]">
                    Se viene la<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-purple-500 to-accent-blue">nueva generación</span>
                </h1>
                <p class="max-w-2xl mx-auto mt-6 text-base sm:text-lg text-text-secondary leading-relaxed">
                    Estamos rehaciendo SheManagerGame por dentro y por fuera: mismos datos, mismas ligas y
                    jugadoras reales, pero con menús nuevos, más chulos y, sobre todo, funcionales.
                    Esta página es el escaparate del proyecto mientras tanto.
                </p>
                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <a href="{{ route('register') }}" class="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-bold bg-purple-600 text-white hover:bg-purple-500 shadow-xl shadow-purple-600/30">Jugar a la versión actual</a>
                    <a href="https://github.com/izandhh15/SheManagerGame" target="_blank" rel="noopener" class="w-full sm:w-auto px-8 py-4 rounded-xl text-base font-semibold border border-border-strong hover:bg-surface-700">Ver el proyecto en GitHub</a>
                </div>
                <p class="mt-4 text-xs text-text-muted">El juego en vivo sigue accesible desde “Jugar”. Esta página no sustituye al juego actual: solo lo anuncia.</p>

                @php $heroLogos = ['ESP1','ENG1','UCL','BRA1','FRA1','LIBERTADORES','USA1','DEU1','ITA1','MEX1','ARG1']; @endphp
                <div class="mt-10 flex flex-wrap items-center justify-center gap-3 sm:gap-4">
                    @foreach($heroLogos as $logoId)
                        <img src="{{ \App\Support\CompetitionLogos::url($logoId) }}" alt="{{ $logoId }}" class="w-11 h-11 sm:w-14 sm:h-14 rounded-xl shadow-lg" loading="lazy">
                    @endforeach
                </div>
            </div>
        </section>

        <section class="max-w-7xl mx-auto px-4 py-12 sm:py-16 w-full">
            <h2 class="font-heading text-2xl sm:text-3xl font-bold uppercase tracking-wide text-center">El nuevo menú</h2>
            <p class="text-center text-text-muted mt-2">Así se va a organizar la nueva interfaz. Todo lo que ya tenías sigue dentro, en su sitio.</p>
            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="p-6 rounded-2xl bg-surface-800 border border-border-default">
                    <p class="text-3xl mb-2">🏟️</p>
                    <h3 class="font-heading font-bold text-lg uppercase tracking-wide">Mi club</h3>
                    <p class="mt-2 text-sm text-text-secondary">Plantilla, alineación, táctica, cantera, estadio y finanzas. Lo de siempre, con menús más claros y rápidos.</p>
                </div>
                <div class="p-6 rounded-2xl bg-surface-800 border border-purple-500/40">
                    <p class="text-3xl mb-2">🌐</p>
                    <h3 class="font-heading font-bold text-lg uppercase tracking-wide">Internet · nuevo</h3>
                    <p class="mt-2 text-sm text-text-secondary">Publica lo que quieras, con tus palabras, en mensajes de hasta 200 caracteres con contador en vivo. Prensa, fichajes, partidos y jugadoras en un solo feed.</p>
                </div>
                <div class="p-6 rounded-2xl bg-surface-800 border border-purple-500/40">
                    <p class="text-3xl mb-2">🎤</p>
                    <h3 class="font-heading font-bold text-lg uppercase tracking-wide">Rueda de prensa · nuevo</h3>
                    <p class="mt-2 text-sm text-text-secondary">Contesta lo que quieras, antes y después del partido. La prensa y la afición reaccionan a tus palabras: si culpas al árbitro tras perder, no sale gratis.</p>
                </div>
                <div class="p-6 rounded-2xl bg-surface-800 border border-border-default">
                    <p class="text-3xl mb-2">📰</p>
                    <h3 class="font-heading font-bold text-lg uppercase tracking-wide">Prensa y medios</h3>
                    <p class="mt-2 text-sm text-text-secondary">Medios reales con sus logos (Marca, AS, Relevo, Mundo Deportivo, Sport, BBC Sport, ESPN, DAZN…) cubriendo tu carrera. Las opiniones dentro del juego son simuladas, no declaraciones reales.</p>
                </div>
                <div class="p-6 rounded-2xl bg-surface-800 border border-border-default">
                    <p class="text-3xl mb-2">🏆</p>
                    <h3 class="font-heading font-bold text-lg uppercase tracking-wide">Competiciones</h3>
                    <p class="mt-2 text-sm text-text-secondary">Liga F, WSL, Première Ligue, Bundesliga, Serie A, NWSL, Brasileirão, Champions, Europa Cup, Libertadores y Mundial 2027, con sus logos.</p>
                </div>
                <div class="p-6 rounded-2xl bg-surface-800 border border-border-default">
                    <p class="text-3xl mb-2">💻</p>
                    <h3 class="font-heading font-bold text-lg uppercase tracking-wide">Juega en local</h3>
                    <p class="mt-2 text-sm text-text-secondary">Bájate el repo y juega en tu ordenador sin depender de la web, con Docker. Guía en <code>docs/local-play.md</code> dentro del proyecto.</p>
                </div>
            </div>
        </section>

        <section id="local" class="border-t border-border-default bg-surface-800/30">
            <div class="max-w-3xl mx-auto px-4 py-12 text-center">
                <h2 class="font-heading text-2xl sm:text-3xl font-bold uppercase tracking-wide">Jugar en local</h2>
                <p class="mt-3 text-text-secondary text-sm leading-relaxed">Clona el repo, levanta Postgres + Redis con Docker y arranca la app. La guía completa está en el repositorio, en <code>docs/local-play.md</code>.</p>
                <p class="mt-4 text-xs text-text-muted font-mono bg-surface-900 border border-border-default rounded-lg px-4 py-3 text-left">git clone https://github.com/izandhh15/SheManagerGame.git<br>cd SheManagerGame<br>docker compose -f docker-compose.dev.yml up</p>
            </div>
        </section>
    </main>

    <footer class="border-t border-border-default">
        <div class="max-w-7xl mx-auto px-4 py-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <span class="text-sm text-text-muted">SheManagerGame — fútbol femenino, jugadoras reales, gratis.</span>
            <div class="flex items-center gap-4 text-xs text-text-muted">
                <a href="https://github.com/izandhh15/SheManagerGame" target="_blank" rel="noopener" class="hover:text-text-secondary">GitHub</a>
                <a href="https://instagram.com/shemanagergame" target="_blank" rel="noopener" class="hover:text-text-secondary">Instagram</a>
                <a href="https://x.com/SheManagerGame" target="_blank" rel="noopener" class="hover:text-text-secondary">X</a>
            </div>
        </div>
    </footer>
</div>
</body>
</html>

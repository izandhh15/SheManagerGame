<x-admin-layout>
    <div class="flex items-center gap-3 mb-1">
        <span class="relative flex h-3 w-3">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
        </span>
        <h1 class="font-heading text-2xl lg:text-3xl font-bold uppercase tracking-wide text-text-primary">
            {{ __('admin.live_title') }}
        </h1>
    </div>
    <p class="text-sm text-text-muted mb-6">
        {{ __('admin.live_subtitle') }} ·
        <span id="live-updated-ago" class="text-text-secondary"></span>
    </p>

    {{-- Contadores principales --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-surface-800 border border-emerald-500/40 rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.live_online_now') }}</div>
            <div id="live-online-total" class="font-heading text-3xl font-bold text-emerald-400">{{ $snapshot['online_total'] }}</div>
            <div class="text-xs text-text-muted mt-1">
                <span id="live-online-registered">{{ $snapshot['online_registered'] }}</span> {{ __('admin.live_registered') }} ·
                <span id="live-online-anonymous">{{ $snapshot['online_anonymous'] }}</span> {{ __('admin.live_anonymous') }}
            </div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.live_today') }}</div>
            <div id="live-today-visits" class="font-heading text-3xl font-bold text-text-primary">{{ number_format($snapshot['today_visits']) }}</div>
            <div class="text-xs text-text-muted mt-1">
                <span id="live-today-uniques">{{ number_format($snapshot['today_uniques']) }}</span> {{ __('admin.live_uniques') }}
            </div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.live_yesterday') }}</div>
            <div id="live-yesterday-visits" class="font-heading text-3xl font-bold text-text-primary">{{ number_format($snapshot['yesterday_visits']) }}</div>
            <div class="text-xs text-text-muted mt-1">
                <span id="live-yesterday-uniques">{{ number_format($snapshot['yesterday_uniques']) }}</span> {{ __('admin.live_uniques') }}
            </div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <div class="text-xs text-text-muted uppercase tracking-wider mb-1">{{ __('admin.live_total_visits') }}</div>
            <div id="live-total-visits" class="font-heading text-3xl font-bold text-accent-primary">{{ number_format($snapshot['total_visits']) }}</div>
        </div>
    </div>

    {{-- Quién está conectado --}}
    <div class="bg-surface-800 border border-border-default rounded-xl p-4 mb-8">
        <h2 class="font-heading text-lg font-bold uppercase tracking-wider text-text-primary mb-4">
            {{ __('admin.live_who_is_online') }}
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-text-muted uppercase tracking-wider border-b border-border-default">
                        <th class="pb-2 pr-4">{{ __('admin.live_user') }}</th>
                        <th class="pb-2 pr-4">{{ __('admin.live_activity') }}</th>
                        <th class="pb-2 pr-4">{{ __('admin.live_device') }}</th>
                        <th class="pb-2 pr-4">{{ __('admin.live_page') }}</th>
                        <th class="pb-2">{{ __('admin.live_last_seen') }}</th>
                    </tr>
                </thead>
                <tbody id="visitors-tbody"></tbody>
            </table>
            <p id="live-empty" class="text-sm text-text-muted py-4 {{ $snapshot['online_total'] > 0 ? 'hidden' : '' }}">
                {{ __('admin.live_no_one') }}
            </p>
        </div>
    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <h2 class="font-heading text-lg font-bold uppercase tracking-wider text-text-primary mb-1">
                {{ __('admin.live_last_7_days') }}
            </h2>
            <div class="flex items-center gap-4 text-xs text-text-muted mb-3">
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>{{ __('admin.live_visits') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block w-2.5 h-2.5 rounded-sm bg-sky-500"></span>{{ __('admin.live_uniques') }}</span>
            </div>
            <div id="chart-7d" class="flex items-end gap-2 h-44"></div>
        </div>
        <div class="bg-surface-800 border border-border-default rounded-xl p-4">
            <h2 class="font-heading text-lg font-bold uppercase tracking-wider text-text-primary mb-4">
                {{ __('admin.live_last_24_hours') }}
            </h2>
            <div id="chart-24h" class="flex items-end gap-1 h-44"></div>
        </div>
    </div>

<script>
(function () {
    const T = @json([
        'activity' => [
            'home' => __('admin.live_activity_home'),
            'playing' => __('admin.live_activity_playing'),
            'creating' => __('admin.live_activity_creating'),
            'entering' => __('admin.live_activity_entering'),
            'browsing' => __('admin.live_activity_browsing'),
        ],
        'device' => [
            'desktop' => __('admin.live_device_desktop'),
            'mobile' => __('admin.live_device_mobile'),
            'tablet' => __('admin.live_device_tablet'),
        ],
        'anonymous' => __('admin.live_anonymous_label'),
        'noOne' => __('admin.live_no_one'),
        'updatedAgo' => __('admin.live_updated_ago'),
        'visits' => __('admin.live_visits'),
        'uniques' => __('admin.live_uniques'),
    ]);
    const DATA_URL = @json(route('admin.live.data'));
    const BASE_TITLE = document.title;

    let lastSnapshot = @json($snapshot);
    let lastGeneratedAt = null;

    const $ = (id) => document.getElementById(id);
    const fmt = (n) => Number(n).toLocaleString(document.documentElement.lang || 'es');

    function renderCharts(snap) {
        // Últimos 7 días: barras agrupadas (visitas + únicos)
        const c7 = $('chart-7d');
        c7.innerHTML = '';
        const max7 = Math.max(1, ...snap.last_7_days.map(d => d.visits));
        const pct7 = (v) => (v > 0 ? Math.max(4, Math.round((v / max7) * 100)) : 4) + '%';
        snap.last_7_days.forEach(d => {
            const col = document.createElement('div');
            col.className = 'flex-1 flex flex-col items-center justify-end h-full min-w-0';
            const bars = document.createElement('div');
            bars.className = 'flex items-end justify-center gap-1 w-full flex-1 min-h-0';
            const b1 = document.createElement('div');
            b1.className = 'w-1/2 max-w-[18px] rounded-t bg-emerald-500';
            b1.style.height = pct7(d.visits);
            b1.title = T.visits + ': ' + d.visits;
            const b2 = document.createElement('div');
            b2.className = 'w-1/2 max-w-[18px] rounded-t bg-sky-500';
            b2.style.height = pct7(d.uniques);
            b2.title = T.uniques + ': ' + d.uniques;
            bars.appendChild(b1);
            bars.appendChild(b2);
            const lab = document.createElement('div');
            lab.className = 'text-[10px] text-text-muted mt-1 truncate w-full text-center';
            lab.textContent = d.label;
            col.appendChild(bars);
            col.appendChild(lab);
            c7.appendChild(col);
        });

        // Últimas 24 horas: una barra por hora
        const c24 = $('chart-24h');
        c24.innerHTML = '';
        const max24 = Math.max(1, ...snap.last_24_hours.map(h => h.visits));
        snap.last_24_hours.forEach((h, i) => {
            const col = document.createElement('div');
            col.className = 'flex-1 flex flex-col items-center justify-end h-full min-w-0';
            const b = document.createElement('div');
            b.className = 'w-full max-w-[14px] rounded-t bg-emerald-500/80';
            b.style.height = (h.visits > 0 ? Math.max(4, Math.round((h.visits / max24) * 100)) : 4) + '%';
            b.title = h.label + ' · ' + T.visits + ': ' + h.visits;
            const lab = document.createElement('div');
            lab.className = 'text-[10px] text-text-muted mt-1 truncate w-full text-center';
            lab.textContent = i % 3 === 0 ? h.label : '';
            col.appendChild(b);
            col.appendChild(lab);
            c24.appendChild(col);
        });
    }

    function renderVisitors(snap) {
        const tb = $('visitors-tbody');
        tb.innerHTML = '';
        $('live-empty').classList.toggle('hidden', snap.online_total > 0);

        snap.online_visitors.forEach(v => {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-border-default/50 last:border-0';

            const tdUser = document.createElement('td');
            tdUser.className = 'py-2.5 pr-4';
            if (v.anonymous) {
                const anon = document.createElement('span');
                anon.className = 'text-text-muted italic';
                anon.textContent = T.anonymous;
                tdUser.appendChild(anon);
            } else {
                const name = document.createElement('div');
                name.className = 'font-medium text-text-primary';
                name.textContent = v.name || '?';
                const email = document.createElement('div');
                email.className = 'text-xs text-text-muted';
                email.textContent = v.email || '';
                tdUser.appendChild(name);
                tdUser.appendChild(email);
            }

            const tdAct = document.createElement('td');
            tdAct.className = 'py-2.5 pr-4';
            const badge = document.createElement('span');
            badge.className = 'inline-block px-2 py-0.5 rounded-full text-xs font-medium ' +
                (v.playing ? 'bg-emerald-500/20 text-emerald-300' : 'bg-surface-700 text-text-secondary');
            badge.textContent = (v.playing ? '🎮 ' : '') + (T.activity[v.activity] || v.activity);
            tdAct.appendChild(badge);

            const tdDev = document.createElement('td');
            tdDev.className = 'py-2.5 pr-4 text-text-secondary text-xs';
            tdDev.textContent = T.device[v.device] || v.device;

            const tdPage = document.createElement('td');
            tdPage.className = 'py-2.5 pr-4 text-text-secondary text-xs font-mono truncate max-w-[220px]';
            tdPage.textContent = v.path;
            tdPage.title = v.path;

            const tdSeen = document.createElement('td');
            tdSeen.className = 'py-2.5 text-text-muted text-xs whitespace-nowrap';
            tdSeen.textContent = v.last_seen_human;

            tr.appendChild(tdUser);
            tr.appendChild(tdAct);
            tr.appendChild(tdDev);
            tr.appendChild(tdPage);
            tr.appendChild(tdSeen);
            tb.appendChild(tr);
        });
    }

    function render(snap) {
        lastSnapshot = snap;
        lastGeneratedAt = new Date(snap.generated_at);
        $('live-online-total').textContent = fmt(snap.online_total);
        $('live-online-registered').textContent = fmt(snap.online_registered);
        $('live-online-anonymous').textContent = fmt(snap.online_anonymous);
        $('live-today-visits').textContent = fmt(snap.today_visits);
        $('live-today-uniques').textContent = fmt(snap.today_uniques);
        $('live-yesterday-visits').textContent = fmt(snap.yesterday_visits);
        $('live-yesterday-uniques').textContent = fmt(snap.yesterday_uniques);
        $('live-total-visits').textContent = fmt(snap.total_visits);
        document.title = '(' + snap.online_total + ') ' + BASE_TITLE.replace(/^\(\d+\) /, '');
        renderVisitors(snap);
        renderCharts(snap);
        tickAgo();
    }

    function tickAgo() {
        if (!lastGeneratedAt) return;
        const s = Math.max(0, Math.round((Date.now() - lastGeneratedAt.getTime()) / 1000));
        $('live-updated-ago').textContent = T.updatedAgo.replace(':seconds', s);
    }

    async function refresh() {
        try {
            const res = await fetch(DATA_URL, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (res.ok) render(await res.json());
        } catch (e) { /* reintenta en el siguiente ciclo */ }
    }

    render(lastSnapshot);
    setInterval(refresh, 10000);
    setInterval(tickAgo, 1000);
})();
</script>
</x-admin-layout>

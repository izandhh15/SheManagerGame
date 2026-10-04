<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra un "latido" por visitante y contadores de tráfico para el
 * panel admin "En directo". Sin cookies ni IP en crudo: el visitante se
 * identifica con el hash de IP + user-agent.
 *
 * Las rutas de administración no se trackean para no contaminar las
 * estadísticas (el propio Izan mirando el panel no contaría como visita).
 */
class TrackVisitors
{
    private const ONLINE_EXCLUDED_PREFIXES = ['admin/', 'editor/'];

    private const BOT_PATTERN = '/bot|crawl|spider|slurp|mediapartners|baidu|yandex|sogou|exabot|facebot|facebookexternalhit|ia_archiver|semrush|ahrefs|mj12bot|dotbot|petalbot|bytespider|gptbot|claudebot|ccbot|headless/i';

    /**
     * Minutos entre escrituras de latido por visitante. El panel "En directo"
     * considera online a quien tenga latido en los ultimos 5 minutos, asi que
     * con 3 el visitante activo nunca se cae del panel mientras los polls
     * AJAX (partido en directo) dejan de generar un upsert por request.
     */
    private const HEARTBEAT_THROTTLE_MINUTES = 3;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Runs after the response is sent: tracking never blocks the visitor.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->track($request);
        } catch (\Throwable) {
            // El tracking nunca debe romper la petición del visitante.
        }
    }

    private function track(Request $request): void
    {
        $path = ltrim($request->path(), '/');

        foreach (self::ONLINE_EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        if ($path === 'up') {
            return;
        }

        $userAgent = substr((string) $request->userAgent(), 0, 255);
        if ($userAgent !== '' && preg_match(self::BOT_PATTERN, $userAgent)) {
            return;
        }

        $now = now();
        $visitorKey = hash('sha256', $request->ip().'|'.$userAgent);
        $device = $this->detectDevice($userAgent);
        $page = '/'.substr($path === '' ? '' : $path, 0, 240);
        $userId = $request->user()?->getAuthIdentifier();

        // Latido: como mucho una escritura cada HEARTBEAT_THROTTLE_MINUTES por
        // visitante. Sin esto, cada request (incluidos POSTs y los polls AJAX
        // del partido en directo) hacia un upsert a Neon.
        $throttleKey = 'visitor_hb:'.$visitorKey;
        if (! Cache::has($throttleKey)) {
            DB::table('visitor_heartbeats')->upsert(
                [[
                    'visitor_key' => $visitorKey,
                    'user_id' => $userId,
                    'path' => $page,
                    'device' => $device,
                    'first_seen' => $now,
                    'last_seen' => $now,
                ]],
                ['visitor_key'],
                ['user_id', 'path', 'device', 'last_seen']
            );
            Cache::put($throttleKey, true, $now->copy()->addMinutes(self::HEARTBEAT_THROTTLE_MINUTES));
        }

        // Contadores solo en GET (vistas de página, no acciones).
        if ($request->isMethod('get')) {
            $this->countVisit($visitorKey, $now);
        }

        // Limpieza probabilística para que las tablas no crezcan sin límite.
        if (random_int(1, 200) === 1) {
            DB::table('visitor_heartbeats')->where('last_seen', '<', $now->copy()->subDay())->delete();
            DB::table('traffic_visitor_days')->where('date', '<', $now->copy()->subDays(120)->toDateString())->delete();
        }
    }

    private function countVisit(string $visitorKey, \Carbon\CarbonImmutable|\Carbon\Carbon $now): void
    {
        $date = $now->toDateString();
        $hour = $now->copy()->startOfHour();

        DB::table('traffic_daily')->upsert(
            [['date' => $date, 'visits' => 1, 'uniques' => 0]],
            ['date'],
            ['visits' => DB::raw('traffic_daily.visits + 1')]
        );

        DB::table('traffic_hourly')->upsert(
            [['hour' => $hour, 'visits' => 1]],
            ['hour'],
            ['visits' => DB::raw('traffic_hourly.visits + 1')]
        );

        $isNewVisitorToday = DB::table('traffic_visitor_days')->insertOrIgnore([
            'date' => $date,
            'visitor_key' => $visitorKey,
        ]);

        if ($isNewVisitorToday) {
            DB::table('traffic_daily')->where('date', $date)->increment('uniques');
        }
    }

    private function detectDevice(string $userAgent): string
    {
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/mobile|iphone|ipod|android.*mobile|windows phone/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}

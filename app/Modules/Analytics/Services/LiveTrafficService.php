<?php

namespace App\Modules\Analytics\Services;

use Illuminate\Support\Facades\DB;

/**
 * Foto del tráfico en tiempo real para el panel admin "En directo".
 */
class LiveTrafficService
{
    public const ONLINE_WINDOW_MINUTES = 5;

    public function getSnapshot(): array
    {
        $now = now();
        $threshold = $now->copy()->subMinutes(self::ONLINE_WINDOW_MINUTES);

        $online = DB::table('visitor_heartbeats')
            ->leftJoin('users', 'users.id', '=', 'visitor_heartbeats.user_id')
            ->where('visitor_heartbeats.last_seen', '>=', $threshold)
            ->orderByDesc('visitor_heartbeats.last_seen')
            ->limit(60)
            ->get([
                'visitor_heartbeats.path',
                'visitor_heartbeats.device',
                'visitor_heartbeats.last_seen',
                'visitor_heartbeats.first_seen',
                'users.name as user_name',
                'users.email as user_email',
            ]);

        $onlineRegistered = $online->whereNotNull('user_name')->count();

        $today = $now->toDateString();
        $todayRow = DB::table('traffic_daily')->where('date', $today)->first();
        $yesterdayRow = DB::table('traffic_daily')->where('date', $now->copy()->subDay()->toDateString())->first();

        return [
            'online_total' => $online->count(),
            'online_registered' => $onlineRegistered,
            'online_anonymous' => $online->count() - $onlineRegistered,
            'online_visitors' => $online->map(fn ($v) => $this->presentVisitor($v, $now))->all(),
            'today_visits' => (int) ($todayRow->visits ?? 0),
            'today_uniques' => (int) ($todayRow->uniques ?? 0),
            'yesterday_visits' => (int) ($yesterdayRow->visits ?? 0),
            'yesterday_uniques' => (int) ($yesterdayRow->uniques ?? 0),
            'last_7_days' => $this->last7Days($now),
            'last_24_hours' => $this->last24Hours($now),
            'total_visits' => (int) DB::table('traffic_daily')->sum('visits'),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    private function presentVisitor(object $v, \Carbon\CarbonInterface $now): array
    {
        $path = $v->path === '/' ? '/' : rtrim($v->path, '/');

        return [
            'name' => $v->user_name,
            'email' => $v->user_email,
            'anonymous' => $v->user_name === null,
            'device' => $v->device,
            'path' => $path === '' ? '/' : $path,
            'activity' => $this->activityLabel($path),
            'playing' => str_starts_with(ltrim($path, '/'), 'game/'),
            'last_seen' => $v->last_seen,
            'last_seen_human' => \Carbon\Carbon::parse($v->last_seen)->diffForHumans(),
        ];
    }

    private function activityLabel(string $path): string
    {
        $p = ltrim($path, '/');

        if ($p === '') {
            return 'home';
        }

        if (str_starts_with($p, 'game/')) {
            return 'playing';
        }

        if (str_starts_with($p, 'new-game')) {
            return 'creating';
        }

        if (in_array($p, ['login', 'register'], true)) {
            return 'entering';
        }

        return 'browsing';
    }

    private function last7Days(\Carbon\CarbonInterface $now): array
    {
        $rows = DB::table('traffic_daily')
            ->where('date', '>=', $now->copy()->subDays(6)->toDateString())
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->toDateString();
            $row = $rows->get($date);
            $days[] = [
                'label' => $now->copy()->subDays($i)->isoFormat('dd D'),
                'visits' => (int) ($row->visits ?? 0),
                'uniques' => (int) ($row->uniques ?? 0),
            ];
        }

        return $days;
    }

    private function last24Hours(\Carbon\CarbonInterface $now): array
    {
        $start = $now->copy()->subHours(23)->startOfHour();
        $rows = DB::table('traffic_hourly')
            ->where('hour', '>=', $start)
            ->orderBy('hour')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[\Carbon\Carbon::parse($r->hour)->format('Y-m-d H:00')] = (int) $r->visits;
        }

        $hours = [];
        for ($i = 23; $i >= 0; $i--) {
            $hour = $now->copy()->subHours($i)->startOfHour();
            $hours[] = [
                'label' => $hour->format('H:00'),
                'visits' => $map[$hour->format('Y-m-d H:00')] ?? 0,
            ];
        }

        return $hours;
    }
}

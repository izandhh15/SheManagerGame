<?php

namespace App\Http\Views;

use App\Models\Game;
use App\Modules\Competition\Services\CalendarService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Monthly calendar grid: every match of the month on its day, with
 * prev/next month navigation. Complements the list-style calendar.
 */
class ShowMonthCalendar
{
    public function __construct(
        private readonly CalendarService $calendarService,
    ) {}

    public function __invoke(Request $request, string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);

        // ?ym=2026-09 — defaults to the game's current month.
        $ym = $request->query('ym', '');
        if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $base = $game->current_date ?? now();
            $ym = $base->format('Y-m');
        }
        [$year, $month] = array_map('intval', explode('-', $ym));
        $month = max(1, min(12, $month));
        $first = Carbon::create($year, $month, 1)->startOfDay();
        $last = $first->copy()->endOfMonth();

        $fixtures = $this->calendarService->getTeamFixtures($game)
            ->filter(fn ($m) => empty($m->is_placeholder)
                && $m->scheduled_date
                && $m->scheduled_date->gte($first)
                && $m->scheduled_date->lte($last))
            ->sortBy('scheduled_date')
            ->values();

        // Group by Y-m-d for the grid.
        $byDay = [];
        foreach ($fixtures as $m) {
            $byDay[$m->scheduled_date->format('Y-m-d')][] = $m;
        }

        $prevYm = $first->copy()->subMonth()->format('Y-m');
        $nextYm = $first->copy()->addMonth()->format('Y-m');
        // Monday-first offset for the leading blanks.
        $leadBlanks = ($first->dayOfWeekIso - 1);
        $daysInMonth = $first->daysInMonth;
        $today = ($game->current_date ?? now())->format('Y-m-d');

        return view('calendar-month', [
            'game' => $game,
            'ym' => $first->format('Y-m'),
            'monthLabel' => $first->translatedFormat('F Y'),
            'byDay' => $byDay,
            'prevYm' => $prevYm,
            'nextYm' => $nextYm,
            'leadBlanks' => $leadBlanks,
            'daysInMonth' => $daysInMonth,
            'today' => $today,
        ]);
    }
}

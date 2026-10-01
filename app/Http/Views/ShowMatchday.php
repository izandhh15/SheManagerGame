<?php

namespace App\Http\Views;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameMatch;
use App\Modules\Stadium\Services\MatchAttendanceService;
use App\Modules\Stadium\Services\MatchdayPricingService;

class ShowMatchday
{
    public function __construct(
        private readonly MatchdayPricingService $pricing,
        private readonly MatchAttendanceService $attendanceService,
    ) {}

    public function __invoke(string $gameId)
    {
        $game = Game::with('team')->findOrFail($gameId);
        abort_if($game->isTournamentMode(), 404);

        $prices = $this->pricing->prices($game);

        // Next home fixture, for the revenue projection.
        $nextHome = GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->orderBy('scheduled_date')
            ->with('awayTeam')
            ->first();

        $projection = null;
        if ($nextHome !== null) {
            $att = $this->attendanceService->projectForMatch($nextHome, $game);
            if ($att !== null) {
                $revenue = $this->pricing->revenueForAttendance((int) $att['attendance'], $prices);
                $projection = [
                    'match' => $nextHome,
                    'attendance' => (int) $att['attendance'],
                    'capacity' => (int) $att['capacity'],
                    'revenue' => $revenue,
                ];
            }
        }

        // Recent matchday income lines (tickets, shirts, merch, bars).
        $recent = FinancialTransaction::where('game_id', $game->id)
            ->where('type', FinancialTransaction::TYPE_INCOME)
            ->whereIn('category', [
                FinancialTransaction::CATEGORY_MATCHDAY_TICKETS,
                FinancialTransaction::CATEGORY_MATCHDAY_SHIRTS,
                FinancialTransaction::CATEGORY_MATCHDAY_MERCH,
                FinancialTransaction::CATEGORY_MATCHDAY_BARS,
            ])
            ->orderByDesc('transaction_date')
            ->limit(12)
            ->get();

        return view('club.matchday', [
            'game' => $game,
            'prices' => $prices,
            'projection' => $projection,
            'recent' => $recent,
            'bands' => [
                'ticket' => [MatchdayPricingService::MIN_TICKET, MatchdayPricingService::MAX_TICKET],
                'shirt' => [MatchdayPricingService::MIN_SHIRT, MatchdayPricingService::MAX_SHIRT],
                'merch' => [MatchdayPricingService::MIN_MERCH, MatchdayPricingService::MAX_MERCH],
                'bar' => [MatchdayPricingService::MIN_BAR, MatchdayPricingService::MAX_BAR],
            ],
            'defaults' => [
                'ticket' => MatchdayPricingService::DEFAULT_TICKET,
                'shirt' => MatchdayPricingService::DEFAULT_SHIRT,
                'merch' => MatchdayPricingService::DEFAULT_MERCH,
                'bar' => MatchdayPricingService::DEFAULT_BAR,
            ],
        ]);
    }
}

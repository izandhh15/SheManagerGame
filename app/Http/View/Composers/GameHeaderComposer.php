<?php

namespace App\Http\View\Composers;

use App\Models\Game;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\View\View;

/**
 * Provides the game header component with its data.
 *
 * The header renders on every game page and used to run 4+ queries inline
 * in the blade (team competitions, unread-notification count, recent
 * notifications, critical-alert group). They live here now, memoized per
 * game for the duration of the request, so repeated renders of the
 * component cost zero extra queries.
 */
class GameHeaderComposer
{
    /** @var array<string, array> game_id => composed data */
    private static array $memo = [];

    public function compose(View $view): void
    {
        $game = $view->getData()['game'] ?? null;

        if (! $game instanceof Game) {
            return;
        }

        self::$memo[$game->id] ??= $this->composeForGame($game);

        $view->with(self::$memo[$game->id]);
    }

    /**
     * @return array{teamCompetitions: \Illuminate\Support\Collection, unreadCount: int, recentNotifications: \Illuminate\Support\Collection, criticalAlerts: \Illuminate\Support\Collection}
     */
    private function composeForGame(Game $game): array
    {
        // Competitions the team participates in for this game
        $teamCompetitions = \App\Models\Competition::whereIn(
            'id',
            $game->competitionEntries()
                ->where('team_id', $game->team_id)
                ->pluck('competition_id')
        )->orderBy('tier')->get();

        // Notifications for the mobile bell icon + modal. The modal mirrors the
        // dashboard inbox: the current matchday's (unread) notifications only.
        $unreadCount = $game->notifications()->whereNull('read_at')->count();
        $recentNotifications = $game->notifications()->unread()->orderByDesc('game_date')->limit(20)->get();

        // Highest-stakes (CRITICAL) notifications that haven't been acknowledged
        // yet surface as a blocking, must-dismiss popup on the next page load.
        $criticalAlerts = app(NotificationService::class)
            ->pendingCriticalAlertGroup($game->id);

        return [
            'teamCompetitions' => $teamCompetitions,
            'unreadCount' => $unreadCount,
            'recentNotifications' => $recentNotifications,
            'criticalAlerts' => $criticalAlerts,
        ];
    }
}

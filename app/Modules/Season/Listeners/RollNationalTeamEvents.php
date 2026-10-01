<?php

namespace App\Modules\Season\Listeners;

use App\Models\GameNotification;
use App\Modules\Match\Events\GameDateAdvanced;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Services\NationalTeamEventService;

/**
 * Rolls for unexpected national-team events when the game date advances:
 * - Post-callup injuries (15% per window): forces last-minute replacements
 * - Resignations (3% per season, 0.5% for Spain)
 */
class RollNationalTeamEvents
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(GameDateAdvanced $event): void
    {
        $game = $event->game;

        // Only for national-team games
        if (!$game->team || $game->team->type === 'club') {
            return;
        }

        // Roll for post-callup injuries
        $injuryEvents = NationalTeamEventService::rollPostCallupInjuries($game);
        foreach ($injuryEvents as $injury) {
            $this->notificationService->create(
                game: $game,
                type: GameNotification::TYPE_NATIONAL_TEAM_INJURY,
                title: "🚨 {$injury['player_name']} se ha lesionado",
                message: "{$injury['player_name']} es baja para esta ventana. Necesitas convocar una sustituta.",
                priority: GameNotification::PRIORITY_WARNING,
                metadata: ['player_id' => $injury['player_id'], 'position' => $injury['position']]
            );
        }

        // Roll for resignations (only at season start - January)
        $month = $event->newDate->month;
        if ($month === 1) {
            $resignationEvents = NationalTeamEventService::rollResignations($game);
            foreach ($resignationEvents as $resignation) {
                $this->notificationService->create(
                    game: $game,
                    type: GameNotification::TYPE_NATIONAL_TEAM_RESIGNATION,
                    title: "📢 {$resignation['player_name']} se retira",
                    message: "{$resignation['player_name']} ha anunciado su retirada de la selección.",
                    priority: GameNotification::PRIORITY_INFO,
                    metadata: ['player_id' => $resignation['player_id']]
                );
            }
        }
    }
}

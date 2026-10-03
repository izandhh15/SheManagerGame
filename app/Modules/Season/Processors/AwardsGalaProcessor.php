<?php

namespace App\Modules\Season\Processors;

use App\Models\Game;
use App\Models\GameNotification;
use App\Models\ManagerStats;
use App\Models\ManagerTrophy;
use App\Models\SeasonAward;
use App\Models\SocialPost;
use App\Modules\Media\Services\MediaOutletService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Season\Contracts\SeasonProcessor;
use App\Modules\Season\DTOs\SeasonTransitionData;
use App\Modules\Season\Services\AwardsGalaService;
use Illuminate\Support\Str;

/**
 * Runs the end-of-season awards gala ("Gala de premios").
 *
 * Computes the Balón de Oro, Pichichi, Zamora and MVP winners from the
 * season's real simulated data, persists them as SeasonAward rows, records
 * trophies in the manager's palmarés for winners from the user's team, and
 * announces the gala with a notification and a media social post.
 *
 * Runs before ContractExpirationProcessor (20) frees expired contracts and
 * before StatsResetProcessor (65) wipes the season's stats, so it reads the
 * season as it was played — including players who leave on a free — and the
 * ordering is deterministic (no shared priority with the expiration step).
 */
class AwardsGalaProcessor implements SeasonProcessor
{
    public function __construct(
        private readonly AwardsGalaService $galaService,
        private readonly NotificationService $notificationService,
        private readonly MediaOutletService $mediaOutletService,
    ) {}

    public function priority(): int
    {
        return 19;
    }

    public function process(Game $game, SeasonTransitionData $data): SeasonTransitionData
    {
        $winners = $this->galaService->computeWinners($game);

        if (empty($winners)) {
            return $data;
        }

        $this->persistAwards($game, $winners);
        $created = $this->recordPalmarésTrophies($game, $winners);

        if ($created > 0) {
            ManagerStats::where('game_id', $game->id)->increment('trophies_count', $created);
        }

        $this->notifyGala($game, $winners);
        $this->postGalaNews($game, $winners);

        $data->setMetadata('awards_gala', collect($winners)->map(
            fn (array $w) => [
                'player_id' => $w['player']->id,
                'name' => $w['player']->name,
                'team' => $w['player']->team?->name,
                'detail' => $w['detail'],
            ]
        )->toArray());

        return $data;
    }

    private function persistAwards(Game $game, array $winners): void
    {
        foreach ($winners as $awardKey => $winner) {
            SeasonAward::updateOrCreate(
                [
                    'game_id' => $game->id,
                    'season' => $game->season,
                    'award_key' => $awardKey,
                ],
                [
                    'game_player_id' => $winner['player']->id,
                    'team_id' => $winner['player']->team_id,
                    'detail' => $winner['detail'],
                ]
            );
        }
    }

    /**
     * Awards won by the user's own players go into the manager's palmarés.
     */
    private function recordPalmarésTrophies(Game $game, array $winners): int
    {
        $created = 0;

        foreach ($winners as $awardKey => $winner) {
            $player = $winner['player'];

            if ($player->team_id !== $game->team_id) {
                continue;
            }

            // competition_id stays null (like friendly trophies): the table has a
            // unique index on (game_id, competition_id, season) and NULLs don't
            // collide, so several awards can land in the same season.
            $trophy = ManagerTrophy::firstOrCreate(
                [
                    'game_id' => $game->id,
                    'competition_id' => null,
                    'season' => $game->season,
                    'custom_name' => SeasonAward::displayName($awardKey),
                ],
                [
                    'user_id' => $game->user_id,
                    'team_id' => $game->team_id,
                    'trophy_type' => 'award',
                ]
            );

            if ($trophy->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function notifyGala(Game $game, array $winners): void
    {
        $lines = [];
        foreach (SeasonAward::AWARD_KEYS as $awardKey) {
            if (! isset($winners[$awardKey])) {
                continue;
            }

            $player = $winners[$awardKey]['player'];
            $lines[] = '🏆 '
                . SeasonAward::displayName($awardKey) . ': '
                . $player->name
                . ' (' . ($player->team?->name ?? '') . ') — '
                . $this->headlineFor($awardKey, $winners[$awardKey]['detail']);
        }

        $title = __('season.gala_notification_title', ['season' => $game->season]);

        $message = __('season.gala_notification_message', [
            'season' => $game->season,
            'lines' => implode("\n", $lines),
        ]);

        $this->notificationService->create(
            $game,
            GameNotification::TYPE_AWARDS_GALA,
            $title,
            $message,
            GameNotification::PRIORITY_INFO,
            ['awards' => array_keys($winners)],
            'trophy'
        );
    }

    private function postGalaNews(Game $game, array $winners): void
    {
        $outlet = $this->mediaOutletService->randomOutlet($game);

        $parts = [];
        foreach (SeasonAward::AWARD_KEYS as $awardKey) {
            if (! isset($winners[$awardKey])) {
                continue;
            }

            $player = $winners[$awardKey]['player'];
            $emoji = match ($awardKey) {
                SeasonAward::AWARD_BALLON_DOR => '🌟',
                SeasonAward::AWARD_PICHICHI => '👟',
                SeasonAward::AWARD_ZAMORA => '🧤',
                default => '⭐',
            };
            $parts[] = $emoji . ' ' . SeasonAward::displayName($awardKey) . ': '
                . $player->name . ' (' . ($player->team?->name ?? '') . ')';
        }

        $text = __('season.gala_news_text', [
            'season' => $game->season,
            'parts' => implode("\n", $parts),
        ]);

        SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $outlet,
            'author_handle' => '@' . Str::slug($outlet),
            'text' => $text,
            'sentiment' => 1,
            'likes' => rand(800, 4000),
            'context' => 'awards_gala',
        ]);
    }

    private function headlineFor(string $awardKey, array $detail): string
    {
        return match ($awardKey) {
            SeasonAward::AWARD_PICHICHI => __('season.gala_headline_pichichi', ['goals' => $detail['goals']]),
            SeasonAward::AWARD_ZAMORA => __('season.gala_headline_zamora', ['conceded' => $detail['goals_conceded_per_match']]),
            SeasonAward::AWARD_MVP => __('season.gala_headline_mvp', ['count' => $detail['mvp_awards']]),
            default => __('season.gala_headline_default', [
                'goals' => $detail['goals'],
                'assists' => $detail['assists'],
            ]),
        };
    }
}

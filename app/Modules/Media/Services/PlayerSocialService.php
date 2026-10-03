<?php

namespace App\Modules\Media\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * What the players upload: post-match reactions from the standout
 * performer and occasional lifestyle posts. All generic — never invents
 * stats, always grounded in the actual match result.
 */
class PlayerSocialService
{
    /**
     * After a match, the team's best-rated player posts a reaction.
     * Idempotent per match.
     */
    public function postMatchReaction(Game $game, GameMatch $match): ?SocialPost
    {
        // Which of the user's teams actually played (main or reserve)?
        $teamId = $match->home_team_id === $game->team_id || $match->away_team_id === $game->team_id
            ? $game->team_id
            : ($game->reserve_team_id && ($match->home_team_id === $game->reserve_team_id || $match->away_team_id === $game->reserve_team_id)
                ? $game->reserve_team_id
                : null);
        if (! $teamId || ! $match->played) {
            return null;
        }

        $already = SocialPost::where('game_id', $game->id)
            ->where('match_id', $match->id)
            ->where('context', 'player_reaction')
            ->exists();
        if ($already) {
            return null;
        }

        // Best rated player of the user's team in this match.
        $player = $this->bestPlayer($game, $match, $teamId);
        if (! $player) {
            return null;
        }

        // Same null pattern as the A13 fix (phase 2): a player row without
        // a usable name must not produce a post, because 'author_name' is
        // NOT NULL and we must not invent a placeholder name.
        $name = $player->name;
        if (trim((string) $name) === '') {
            return null;
        }

        $winnerId = $match->getWinnerId();
        $draw = ($match->home_score ?? 0) === ($match->away_score ?? 0);
        $won = $winnerId === $teamId;

        $es = app()->getLocale() === 'es';
        $handle = $this->playerHandle($player);

        if ($es) {
            $texts = $won
                ? [
                    "¡VAMOOOS! Tres puntos más. Esto es de todas 💪🔥",
                    "Qué noche. Gracias a la afición por no callar ni un minuto 🙌",
                    "Seguimos. El objetivo está cada vez más cerca ⚽💙",
                ]
                : ($draw
                    ? [
                        "No era el resultado que queríamos, pero seguimos sumando. A por el siguiente 💪",
                        "Partido duro. Nos quedamos con las ganas, pero el punto vale.",
                    ]
                    : [
                        "Día duro. Pedimos perdón a la afición: volveremos más fuertes 😤",
                        "No salió nada hoy. Toca trabajar en silencio y responder en el campo.",
                    ]);
        } else {
            $texts = $won
                ? [
                    "COME ON! Three more points. This one's for all of us 💪🔥",
                    "What a night. Thanks to the fans for never going quiet 🙌",
                ]
                : ($draw
                    ? ["Not the result we wanted, but we keep adding. On to the next one 💪"]
                    : ["Tough day. Sorry to the fans: we'll come back stronger 😤"]);
        }

        return SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $name,
            'author_handle' => $handle,
            'text' => $texts[array_rand($texts)],
            'sentiment' => $won ? 1 : ($draw ? 0 : -1),
            'likes' => rand(800, 12000),
            'context' => 'player_reaction',
            'match_id' => $match->id,
        ]);
    }

    /**
     * Occasional lifestyle/training post from a random squad player.
     * Called from the advance flow (low probability).
     */
    public function maybePostLifestyle(Game $game): ?SocialPost
    {
        if (rand(1, 100) > 12) {
            return null;
        }

        $player = GamePlayer::where('game_id', $game->id)
            ->where('team_id', $game->team_id)
            ->inRandomOrder()
            ->first();
        if (! $player || trim((string) $player->name) === '') {
            // No attributable author: 'author_name' is NOT NULL and we must
            // not invent a placeholder name. Skip the post silently.
            return null;
        }

        // Don't spam: max 1 lifestyle post per game week.
        $weekAgo = ($game->current_date ?? now())->copy()->subDays(7);
        $recent = SocialPost::where('game_id', $game->id)
            ->where('context', 'player_lifestyle')
            ->where('created_at', '>=', $weekAgo)
            ->exists();
        if ($recent) {
            return null;
        }

        $es = app()->getLocale() === 'es';
        $handle = $this->playerHandle($player);

        $texts = $es ? [
            "Entreno completado ✅ La semana se presenta intensa 💪",
            "Recuperando para lo que viene. Foco total 🔋",
            "Qué suerte jugar con este grupo 🫶",
            "Día de gimnasio. Sin atajos 🏋️‍♀️",
        ] : [
            "Training done ✅ Big week ahead 💪",
            "Recovering for what's next. Full focus 🔋",
        ];

        return SocialPost::create([
            'game_id' => $game->id,
            'author_name' => $player->name,
            'author_handle' => $handle,
            'text' => $texts[array_rand($texts)],
            'sentiment' => 1,
            'likes' => rand(500, 6000),
            'context' => 'player_lifestyle',
        ]);
    }

    /**
     * Build a sane author handle for a player.
     *
     * Same fallback pattern as clubHandle()/nationalHandle(): an emoji-only
     * name leaves an empty body after sanitising, so fall back to a
     * '@jugadora_<id>' handle instead of a bare '@'. The result never
     * exceeds 255 chars (varchar of social_posts.author_handle).
     */
    private function playerHandle(GamePlayer $player): string
    {
        $body = strtolower(preg_replace('/[^a-z0-9]/i', '', str_replace(' ', '', (string) $player->name)));

        if ($body === '') {
            // Short id suffix keeps handles reasonably unique per player.
            $body = 'jugadora_' . substr(str_replace('-', '', (string) $player->id), 0, 8);
        }

        return '@' . mb_substr($body, 0, 254);
    }

    private function bestPlayer(Game $game, GameMatch $match, string $teamId): ?GamePlayer
    {
        // NOTE: never query a possibly-missing table inside the try here.
        // In PostgreSQL a failed query aborts the whole surrounding
        // transaction (MatchdayOrchestrator::advance runs in one), and the
        // catch cannot undo that — the fallback query then dies with 25P02
        // and the entire matchday rolls back. Check existence FIRST.
        // (The old 'match_player_ratings' table was never created; the real
        // ratings live in 'game_player_match_ratings'.)
        if (Schema::hasTable('game_player_match_ratings')) {
            try {
                $bestId = DB::table('game_player_match_ratings as r')
                    ->join('game_players as p', 'p.id', '=', 'r.game_player_id')
                    ->where('r.game_match_id', $match->id)
                    ->where('p.team_id', $teamId)
                    ->orderByDesc('r.rating')
                    ->value('r.game_player_id');
                if ($bestId) {
                    return GamePlayer::where('game_id', $game->id)->find($bestId);
                }
            } catch (\Throwable $e) {
                // Do NOT swallow this: a failed query aborts the whole
                // surrounding PostgreSQL transaction
                // (MatchdayOrchestrator::advance runs in one), and the
                // catch cannot undo that — the fallback query below would
                // then die with 25P02 and mask the real error. Log and
                // re-throw so the outer transaction rolls back cleanly with
                // the original failure.
                Log::warning('PlayerSocialService::bestPlayer ratings query failed', [
                    'game_id' => $game->id,
                    'match_id' => $match->id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->inRandomOrder()
            ->first();
    }
}

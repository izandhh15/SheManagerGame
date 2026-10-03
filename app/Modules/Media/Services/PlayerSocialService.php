<?php

namespace App\Modules\Media\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GamePlayer;
use App\Models\SocialPost;
use Illuminate\Support\Facades\DB;

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
        $teamId = $game->team_id;
        $involved = in_array($match->home_team_id, [$teamId, $game->reserve_team_id], true)
            || in_array($match->away_team_id, [$teamId, $game->reserve_team_id], true);
        if (! $involved || ! $match->played) {
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

        $winnerId = $match->getWinnerId();
        $draw = ($match->home_score ?? 0) === ($match->away_score ?? 0);
        $won = $winnerId === $teamId;

        $es = app()->getLocale() === 'es';
        $name = $player->name;
        $handle = '@' . strtolower(preg_replace('/[^a-z0-9]/i', '', str_replace(' ', '', $name)));

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
        if (! $player) {
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
        $handle = '@' . strtolower(preg_replace('/[^a-z0-9]/i', '', str_replace(' ', '', $player->name)));

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

    private function bestPlayer(Game $game, GameMatch $match, string $teamId): ?GamePlayer
    {
        try {
            $row = DB::table('match_player_ratings')
                ->where('match_id', $match->id)
                ->where('team_id', $teamId)
                ->orderByDesc('rating')
                ->first();
            if ($row && isset($row->player_id)) {
                return GamePlayer::where('game_id', $game->id)->find($row->player_id);
            }
        } catch (\Throwable) {
            // Table may not exist; fall back to random.
        }

        return GamePlayer::where('game_id', $game->id)
            ->where('team_id', $teamId)
            ->inRandomOrder()
            ->first();
    }
}

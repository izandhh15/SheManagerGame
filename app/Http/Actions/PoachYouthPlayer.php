<?php

namespace App\Http\Actions;

use App\Models\AcademyPlayer;
use App\Models\Game;
use Illuminate\Http\Request;

/**
 * Poach a youth player from a rival academy. The player joins the manager's
 * academy (or first team if good enough). Costs a compensation fee based on
 * the player's potential. The rival club may refuse if the fee is too low.
 */
class PoachYouthPlayer
{
    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::with(['team', 'finances'])->findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $prospect = AcademyPlayer::where('game_id', $game->id)
            ->where('id', $playerId)
            ->where('team_id', '!=', $game->team_id)
            ->firstOrFail();

        // Compensation fee: scales with potential.
        $fee = $this->compensationFee($prospect);

        $budget = $game->finances?->transfer_budget ?? 0;
        if ($budget < $fee) {
            return redirect()->back()->with('error',
                app()->getLocale() === 'es'
                    ? "No tienes suficiente presupuesto ({$fee}€ necesarios)."
                    : "Not enough budget ({$fee}€ needed).");
        }

        // Rival club decision: higher potential = more likely to refuse.
        // The fee is fixed, so this is about whether they even negotiate.
        $refuseChance = min(70, ($prospect->potential - 60) * 3);
        if (rand(1, 100) <= $refuseChance) {
            return redirect()->back()->with('error',
                app()->getLocale() === 'es'
                    ? "{$prospect->team?->name} se niega a negociar por {$prospect->name}."
                    : "{$prospect->team?->name} refuses to negotiate for {$prospect->name}.");
        }

        // Success: deduct fee, move player to user's academy.
        // (Simplified: update team_id; a full transfer would create GamePlayer.)
        $prospect->team_id = $game->team_id;
        $prospect->save();

        // Deduct from budget (via finances if available).
        if ($game->finances) {
            $game->finances->transfer_budget = max(0, $budget - $fee);
            $game->finances->save();
        }

        // Social media buzz.
        \App\Models\SocialPost::create([
            'game_id' => $game->id,
            'author_name' => 'Fichajes Fem',
            'author_handle' => '@fichajesfem',
            'text' => app()->getLocale() === 'es'
                ? "🚨 {$game->team?->name} 'roba' a la perla {$prospect->name} ({$prospect->potential} pot.) de la cantera rival."
                : "🚨 {$game->team?->name} 'steals' wonderkid {$prospect->name} ({$prospect->potential} pot.) from a rival academy.",
            'sentiment' => 1,
            'likes' => rand(100, 800),
            'context' => 'youth_poach',
        ]);

        return redirect()->route('game.scouting.youth', $game->id)->with('success',
            app()->getLocale() === 'es'
                ? "¡{$prospect->name} se une a tu cantera!"
                : "{$prospect->name} joins your academy!");
    }

    private function compensationFee(AcademyPlayer $prospect): int
    {
        // Base on potential: 60 pot = 50k, 90 pot = 2M
        $base = 50000;
        $multiplier = max(1, ($prospect->potential - 60) / 10);

        return (int) ($base * $multiplier * $multiplier);
    }
}

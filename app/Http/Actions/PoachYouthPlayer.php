<?php

namespace App\Http\Actions;

use App\Models\AcademyPlayer;
use App\Models\Game;
use App\Models\GameInvestment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Poach a youth player from a rival academy. The player joins the manager's
 * academy (or first team if good enough). Costs a compensation fee based on
 * the player's potential. The rival club may refuse if the fee is too low.
 */
class PoachYouthPlayer
{
    public function __invoke(Request $request, string $gameId, string $playerId)
    {
        $game = Game::with(['team'])->findOrFail($gameId);

        if ((int) $game->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $prospect = AcademyPlayer::where('game_id', $game->id)
            ->where('id', $playerId)
            ->where('team_id', '!=', $game->team_id)
            ->firstOrFail();

        // Compensation fee: scales with potential (euros).
        $feeEuros = $this->compensationFee($prospect);

        // The transfer budget lives on the current season's investment
        // (game_investments.transfer_budget, in cents) — Game::finances()
        // is a HasMany (Collection) and game_finances has no such column.
        $investment = $game->currentInvestment;
        $feeCents = $feeEuros * 100;

        if ($investment === null || (int) $investment->transfer_budget < $feeCents) {
            return redirect()->back()->with('error',
                app()->getLocale() === 'es'
                    ? "No tienes suficiente presupuesto ({$feeEuros}€ necesarios)."
                    : "Not enough budget ({$feeEuros}€ needed).");
        }

        // The budget check above is only a fast path: the authoritative
        // check + the decrement run inside the transaction, on the locked
        // investment row, so two concurrent poaches can't both pass the
        // check and drive the budget negative.
        $result = DB::transaction(function () use ($game, $prospect, $investment, $feeEuros, $feeCents) {
            $lockedInvestment = GameInvestment::whereKey($investment->id)->lockForUpdate()->first();
            if ($lockedInvestment === null || (int) $lockedInvestment->transfer_budget < $feeCents) {
                return ['ok' => false, 'reason' => 'budget'];
            }

            // Lock the prospect too: a concurrent poach of the same player
            // serializes here instead of moving her twice.
            $fresh = AcademyPlayer::whereKey($prospect->id)->lockForUpdate()->first();
            if ($fresh === null || $fresh->team_id === $game->team_id) {
                return ['ok' => false, 'reason' => 'gone'];
            }

            // Rival club decision: higher potential = more likely to refuse.
            // The fee is fixed, so this is about whether they even negotiate.
            $refuseChance = min(70, ($fresh->potential - 60) * 3);
            if (rand(1, 100) <= $refuseChance) {
                // Anti spam-click: a refused approach still costs a scouting
                // fee (10% of the compensation, min €5k). Clicking until the
                // rival caves is no longer free, so the refusal mechanic
                // can't be brute-forced.
                $attemptCents = max(500_000, (int) round($feeCents * 0.10));
                $lockedInvestment->decrement('transfer_budget', $attemptCents);

                return ['ok' => false, 'reason' => 'refused', 'cost' => $attemptCents];
            }

            // Success: deduct fee, move player to user's academy.
            // (Simplified: update team_id; a full transfer would create GamePlayer.)
            $fresh->team_id = $game->team_id;
            $fresh->save();

            // Success: deduct fee from the transfer budget (cents).
            $lockedInvestment->decrement('transfer_budget', $feeCents);

            // Social media buzz.
            \App\Models\SocialPost::create([
                'game_id' => $game->id,
                'author_name' => 'Fichajes Fem',
                'author_handle' => '@fichajesfem',
                'text' => app()->getLocale() === 'es'
                    ? "🚨 {$game->team?->name} 'roba' a la perla {$fresh->name} ({$fresh->potential} pot.) de la cantera rival."
                    : "🚨 {$game->team?->name} 'steals' wonderkid {$fresh->name} ({$fresh->potential} pot.) from a rival academy.",
                'sentiment' => 1,
                'likes' => rand(100, 800),
                'context' => 'youth_poach',
            ]);

            return ['ok' => true, 'player' => $fresh];
        });

        if ($result['ok']) {
            $player = $result['player'];

            return redirect()->route('game.scouting.youth', $game->id)->with('success',
                app()->getLocale() === 'es'
                    ? "¡{$player->name} se une a tu cantera!"
                    : "{$player->name} joins your academy!");
        }

        if (($result['reason'] ?? null) === 'budget') {
            return redirect()->back()->with('error',
                app()->getLocale() === 'es'
                    ? "No tienes suficiente presupuesto ({$feeEuros}€ necesarios)."
                    : "Not enough budget ({$feeEuros}€ needed).");
        }

        if (($result['reason'] ?? null) === 'gone') {
            return redirect()->back()->with('error',
                app()->getLocale() === 'es'
                    ? "{$prospect->name} ya no está disponible."
                    : "{$prospect->name} is no longer available.");
        }

        $costEuros = number_format((int) (($result['cost'] ?? 0) / 100), 0, ',', '.');

        return redirect()->back()->with('error',
            app()->getLocale() === 'es'
                ? "{$prospect->team?->name} se niega a negociar por {$prospect->name}. El acercamiento ha costado {$costEuros}€ en ojeo."
                : "{$prospect->team?->name} refuses to negotiate for {$prospect->name}. The approach cost {$costEuros}€ in scouting.");
    }

    /**
     * Compensation fee in EUROS (converted to cents against the budget).
     * Base on potential: 60 pot = €50k, 90 pot = €450k.
     */
    private function compensationFee(AcademyPlayer $prospect): int
    {
        $base = 50000;
        $multiplier = max(1, ($prospect->potential - 60) / 10);

        return (int) ($base * $multiplier * $multiplier);
    }
}

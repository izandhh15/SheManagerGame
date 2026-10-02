<?php

namespace App\Modules\Competition\Services\Draw;

use App\Modules\Competition\Contracts\CupDrawPairingStrategy;
use Illuminate\Support\Collection;

/**
 * Copa de la Reina–style draw: pair lower-category clubs against
 * higher-category clubs as much as possible, weighing geographic proximity
 * (RFEF) when a region map is provided.
 *
 * Teams are sorted by league tier, split into two halves (higher-category
 * and lower-category). Each lower-half team then draws the first
 * still-available upper-half team from its own region, falling back to the
 * first available team — so cross-category pairing is preserved while
 * nearby clubs meet more often. Without a region map the picks are purely
 * positional after the shuffle, as before.
 *
 * Odd inputs: every team must appear in the output. When the input size is
 * odd, the surplus team falls into the lower half and is appended at the
 * end of the result, leaving it unpaired for the caller to handle.
 * Earlier versions used `slice($half, $half)` for the lower half, which
 * silently dropped the median team and propagated downstream as missing
 * cup ties (one team disappeared per draw with an odd team pool).
 */
class CrossCategoryPairing implements CupDrawPairingStrategy
{
    public function pairTeams(Collection $teams, array $teamTierMap, array $teamSeedMap = [], array $teamRegionMap = []): Collection
    {
        $sorted = $teams
            ->sort(fn ($a, $b) => ($teamTierMap[$a] ?? 99) <=> ($teamTierMap[$b] ?? 99))
            ->values();

        $count = $sorted->count();
        $upperSize = intdiv($count, 2);

        // Higher-ranked teams (lower tier numbers) populate the upper half.
        // Slice without an explicit length so the lower half absorbs every
        // remaining team — including the median when $count is odd.
        $higherHalf = $sorted->slice(0, $upperSize)->shuffle()->values();
        $lowerHalf = $sorted->slice($upperSize)->shuffle()->values();

        $paired = collect();
        $upperRemaining = $higherHalf->values();

        // One pick per upper-half team; the odd-count surplus (if any) stays
        // in the lower half and is appended unpaired below.
        $pairCount = min($upperSize, $lowerHalf->count());
        foreach ($lowerHalf->take($pairCount) as $lowerTeam) {
            $region = $teamRegionMap[$lowerTeam] ?? null;
            $pickKey = ($region !== null)
                ? $upperRemaining->search(fn ($candidate) => ($teamRegionMap[$candidate] ?? null) === $region)
                : false;

            if ($pickKey === false) {
                $pickKey = 0;
            }

            $paired->push($upperRemaining->pull($pickKey));
            $paired->push($lowerTeam);
            $upperRemaining = $upperRemaining->values();
        }

        // Append any surplus from the lower half (only triggers when
        // $count is odd — exactly one team remains).
        foreach ($lowerHalf->skip($pairCount) as $surplusTeam) {
            $paired->push($surplusTeam);
        }

        return $paired;
    }
}

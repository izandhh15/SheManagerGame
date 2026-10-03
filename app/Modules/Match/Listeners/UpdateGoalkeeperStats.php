<?php

namespace App\Modules\Match\Listeners;

use App\Modules\Match\Events\MatchFinalized;
use App\Models\GamePlayer;
use App\Models\GamePlayerMatchState;

/**
 * Attributes goals_conceded / clean_sheets to every goalkeeper who played,
 * not just the starters.
 *
 * Minutes come from the match's substitutions JSON (player_out_id /
 * player_in_id / minute); regulation goals are prorated over regulation
 * minutes and extra-time goals (home_score_et / away_score_et) over ET
 * minutes, so a keeper subbed on at half-time only carries her share and a
 * keeper who only played regulation is untouched by ET goals. A clean
 * sheet requires the team to have conceded nothing in regulation AND in
 * extra time — a keeper whose side only conceded in ET gets no clean
 * sheet. Totals always add up to the real scoreline (largest remainder).
 */
class UpdateGoalkeeperStats
{
    private const REGULATION_MINUTES = 90;
    private const EXTRA_TIME_MINUTES = 30;

    public function handle(MatchFinalized $event): void
    {
        $match = $event->match;
        $homeLineupIds = array_map('strval', $match->home_lineup ?? []);
        $awayLineupIds = array_map('strval', $match->away_lineup ?? []);
        $subs = $match->substitutions ?? [];

        // Which side each substitution belongs to, and every incoming player.
        $subSides = [];
        $incomingIds = [];
        foreach ($subs as $i => $sub) {
            $subSides[$i] = ((string) ($sub['team_id'] ?? '')) === (string) $match->home_team_id
                ? 'home'
                : 'away';
            if (isset($sub['player_in_id'])) {
                $incomingIds[] = (string) $sub['player_in_id'];
            }
        }

        // Every goalkeeper who appeared: starters plus substitutes.
        $gkIds = GamePlayer::whereIn('id', array_merge($homeLineupIds, $awayLineupIds, $incomingIds))
            ->where('position', 'Goalkeeper')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (empty($gkIds)) {
            return;
        }

        $wentToEt = (bool) ($match->is_extra_time ?? false)
            || $match->home_score_et !== null
            || $match->away_score_et !== null;

        $increments = [];
        foreach (['home', 'away'] as $side) {
            $lineupIds = $side === 'home' ? $homeLineupIds : $awayLineupIds;

            $sideGkIds = array_values(array_filter(
                $gkIds,
                fn (string $id) => in_array($id, $lineupIds, true)
                    || $this->enteredAsSubstitute($id, $subs, $subSides, $side)
            ));

            if (empty($sideGkIds)) {
                continue;
            }

            $regMinutes = [];
            $etMinutes = [];
            foreach ($sideGkIds as $gkId) {
                [$regMinutes[$gkId], $etMinutes[$gkId]] =
                    $this->minutesPlayed($gkId, $lineupIds, $subs, $subSides, $side, $wentToEt);
            }

            // Goals this side conceded, split regulation / extra time.
            $concededReg = $side === 'home' ? (int) ($match->away_score ?? 0) : (int) ($match->home_score ?? 0);
            $concededEt = $side === 'home' ? (int) ($match->away_score_et ?? 0) : (int) ($match->home_score_et ?? 0);

            $goalsReg = $this->prorate($concededReg, $regMinutes);
            $goalsEt = $this->prorate($concededEt, $etMinutes);

            $teamTotal = $concededReg + $concededEt;

            foreach ($sideGkIds as $gkId) {
                if (($regMinutes[$gkId] + $etMinutes[$gkId]) <= 0) {
                    continue;
                }

                $increments[$gkId] = [
                    'goals_conceded' => $goalsReg[$gkId] + $goalsEt[$gkId],
                    // No clean sheet when the side conceded at any point,
                    // including only in extra time.
                    'clean_sheets' => $teamTotal === 0 ? 1 : 0,
                ];
            }
        }

        // Filter out goalkeepers with no changes
        $increments = array_filter($increments, fn ($v) => $v['goals_conceded'] !== 0 || $v['clean_sheets'] !== 0);

        if (empty($increments)) {
            return;
        }

        GamePlayerMatchState::bulkIncrementStats($increments);
    }

    private function enteredAsSubstitute(string $gkId, array $subs, array $subSides, string $side): bool
    {
        foreach ($subs as $i => $sub) {
            if ($subSides[$i] !== $side) {
                continue;
            }
            if ((string) ($sub['player_in_id'] ?? '') === $gkId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Regulation and extra-time minutes played by one goalkeeper.
     *
     * @return array{0: int, 1: int} [regulationMinutes, extraTimeMinutes]
     */
    private function minutesPlayed(
        string $gkId,
        array $lineupIds,
        array $subs,
        array $subSides,
        string $side,
        bool $wentToEt,
    ): array {
        $regStart = in_array($gkId, $lineupIds, true) ? 0 : null;
        $regEnd = self::REGULATION_MINUTES;
        $etStart = null;
        $etEnd = self::REGULATION_MINUTES + self::EXTRA_TIME_MINUTES;

        foreach ($subs as $i => $sub) {
            if ($subSides[$i] !== $side) {
                continue;
            }
            $minute = (int) ($sub['minute'] ?? 0);

            if ((string) ($sub['player_out_id'] ?? '') === $gkId) {
                if ($minute <= self::REGULATION_MINUTES) {
                    $regEnd = min($regEnd, $minute);
                } else {
                    $etEnd = min($etEnd, $minute);
                }
            } elseif ((string) ($sub['player_in_id'] ?? '') === $gkId) {
                if ($minute <= self::REGULATION_MINUTES) {
                    if ($regStart === null) {
                        $regStart = $minute;
                    }
                } elseif ($etStart === null || $minute < $etStart) {
                    $etStart = $minute;
                }
            }
        }

        $regMinutes = $regStart === null ? 0 : max(0, $regEnd - $regStart);

        $etMinutes = 0;
        if ($wentToEt) {
            // Still on the pitch at 90' → plays extra time unless subbed off in ET.
            if ($etStart === null && $regStart !== null && $regEnd >= self::REGULATION_MINUTES) {
                $etStart = self::REGULATION_MINUTES;
            }
            $etMinutes = $etStart === null ? 0 : max(0, $etEnd - $etStart);
        }

        return [$regMinutes, $etMinutes];
    }

    /**
     * Split $total goals over keepers proportionally to their minutes.
     * Largest remainder, so the shares always add up to $total.
     *
     * @param  array<string, int>  $minutes  keeperId => minutes
     * @return array<string, int>  keeperId => goals
     */
    private function prorate(int $total, array $minutes): array
    {
        $result = array_fill_keys(array_keys($minutes), 0);
        $sum = array_sum($minutes);

        if ($total <= 0 || $sum <= 0) {
            return $result;
        }

        $remainders = [];
        $distributed = 0;
        foreach ($minutes as $id => $m) {
            $exact = $total * $m / $sum;
            $floored = (int) floor($exact);
            $result[$id] = $floored;
            $remainders[$id] = $exact - $floored;
            $distributed += $floored;
        }

        arsort($remainders);
        $left = $total - $distributed;
        foreach (array_keys($remainders) as $id) {
            if ($left <= 0) {
                break;
            }
            if ($minutes[$id] <= 0) {
                continue;
            }
            $result[$id]++;
            $left--;
        }

        return $result;
    }
}

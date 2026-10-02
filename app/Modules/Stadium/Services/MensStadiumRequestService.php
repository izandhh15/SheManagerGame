<?php

declare(strict_types=1);

namespace App\Modules\Stadium\Services;

use App\Models\ClubProfile;
use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\TeamReputation;
use App\Models\TransferOffer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Jugar en el estadio masculino": the user's women's club asks the men's
 * club to play a home match at a men's stadium (e.g. Valencia Femenino
 * asking to play at Mestalla, or renting La Cartuja from the city council).
 *
 * RESTRICTED MODEL (02-10-2026): each women's club can ONLY rent its
 * own men's team's ground ("la casa del equipo masculino") — the rest of
 * the catalogue is off-limits. Clubs WITHOUT a mapped men's team (e.g.
 * Madrid CFF) keep the old behaviour: rent any stadium from the
 * catalogue, paying full price.
 *
 * Pricing (unchanged):
 *  - Affiliated club (same entity, womens_team link): "precio de la casa"
 *    (50% of the listed price). On huge nights (importance >= 80) the
 *    house invites: free.
 *  - Any other men's ground or municipal stadium: full listed price.
 *
 * Clubs WITHOUT a mapped men's team (e.g. Madrid CFF, a women's-only
 * club) keep the full catalogue — they just pay the full price everywhere.
 *
 * The men's club (AI) still evaluates the match importance (0-100) and may
 * refuse outright for low-profile games:
 *  - Rival ELITE: +40 / CONTINENTAL: +25
 *  - Cup/knockout or title-deciding late-season match: +30
 *  - Derby (same country): +20
 *  - Random: +0-15
 * Accepts if importance >= 50. Max 3 matches per season, 21 days advance.
 */
class MensStadiumRequestService
{
    public const MAX_PER_SEASON = 3;

    public const ACCEPT_THRESHOLD = 50;

    /**
     * Minimum days in advance to request a men's stadium.
     * The owner needs time to organize logistics.
     */
    public const MIN_ADVANCE_DAYS = 21;

    /**
     * Affiliated clubs (same entity) pay this share of the listed price.
     */
    public const AFFILIATED_SHARE = 0.5;

    /**
     * Affiliated + match importance at/above this: the men's club invites
     * (free) — "la casa invita en las grandes noches".
     */
    public const CASA_INVITA_THRESHOLD = 80;

    /** @var array<string, array>|null keyed by stadium|owner composite key */
    private ?array $stadiumsByName = null;

    /** @var array<string, array>|null keyed by women's team name */
    private ?array $affiliatedMap = null;

    /**
     * The men's stadium affiliated with a women's team (same entity), if
     * any. Full row: stadium, capacity, club, owner, rental_price,
     * womens_team.
     */
    public function mensStadiumFor(string $womensTeamName): ?array
    {
        $this->loadStadiums();

        return $this->affiliatedMap[$womensTeamName] ?? null;
    }

    public function hasMensStadium(string $womensTeamName): bool
    {
        return $this->mensStadiumFor($womensTeamName) !== null;
    }

    /**
     * Convert a team country (ISO code like 'ES', or a full name) to the
     * full country name used in the stadium catalogue.
     */
    private function countryName(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        // Already a full name (or unknown code): use as-is.
        $name = config('countries.'.$country.'.name');

        return $name ?? $country;
    }

    /**
     * Full rental catalogue: every men's/municipal ground IN THE USER'S
     * COUNTRY that can be rented, affiliated first (user's own), then the
     * rest by club.
     *
     * 02-10-2026 (0.3.9): the catalogue is open to any ground in the
     * user's own country — but some clubs will refuse with excuses (see
     * isClubDifficult). Only government-paid friendlies (F4) may use
     * grounds abroad.
     *
     * @return list<array>
     */
    public function rentalCatalogue(string $womensTeamName, ?string $userCountry = null): array
    {
        $this->loadStadiums();

        $mine = $this->affiliatedMap[$womensTeamName] ?? null;
        $countryName = $this->countryName($userCountry);

        $list = collect($this->stadiumsByName)
            ->filter(fn (array $s) => $countryName === null || ($s['country'] ?? null) === $countryName);

        if ($mine !== null) {
            $list = $list->sortBy(fn (array $s) => $s['key'] === $mine['key'] ? 0 : 1);
        } else {
            $list = $list->sortBy(fn (array $s) => ($s['club'] ?? $s['owner'] ?? $s['stadium']));
        }

        return $list->values()->all();
    }

    /**
     * Can this women's team rent the stadium with this key? Only grounds
     * in the user's own country (F3); government-paid friendlies bypass
     * this via $allowAbroad.
     */
    public function isRentableBy(string $womensTeamName, string $stadiumKey, ?string $userCountry = null, bool $allowAbroad = false): bool
    {
        $this->loadStadiums();

        $stadium = $this->stadiumsByName[$stadiumKey] ?? null;
        if ($stadium === null) {
            return false;
        }

        if (! $allowAbroad && $userCountry !== null && ($stadium['country'] ?? null) !== $this->countryName($userCountry)) {
            return false;
        }

        return true;
    }

    /**
     * Same-entity check: the stadium belongs to this women's team,
     * including aliases (first team + B/C teams share the ground).
     */
    public function isAffiliated(array $stadium, string $womensTeamName): bool
    {
        if ($womensTeamName === '') {
            return false;
        }

        if (($stadium['womens_team'] ?? null) === $womensTeamName) {
            return true;
        }

        return in_array($womensTeamName, $stadium['womens_teams'] ?? [], true);
    }

    public function stadiumByKey(string $key): ?array
    {
        $this->loadStadiums();

        return $this->stadiumsByName[$key] ?? null;
    }

    public function usesThisSeason(Game $game): int
    {
        return GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->whereNotNull('neutral_venue_name')
            ->where('neutral_venue_name', '!=', '')
            ->count();
    }

    public function canRequest(Game $game): bool
    {
        return $this->usesThisSeason($game) < self::MAX_PER_SEASON;
    }

    /**
     * @return array{accepted: bool, importance: int, reasons: list<string>}
     */
    public function evaluate(GameMatch $match, Game $game): array
    {
        $reasons = [];
        $importance = 0;

        $reputations = TeamReputation::resolveLevels($game->id, [$match->home_team_id, $match->away_team_id]);
        $rivalRep = $reputations->get($match->away_team_id);

        if ($rivalRep === ClubProfile::REPUTATION_ELITE) {
            $importance += 40;
            $reasons[] = 'rival_elite';
        } elseif ($rivalRep === ClubProfile::REPUTATION_CONTINENTAL) {
            $importance += 25;
            $reasons[] = 'rival_continental';
        }

        if ($this->isCupOrKnockout($match) || $this->isTitleDecider($match, $game)) {
            $importance += 30;
            $reasons[] = $this->isCupOrKnockout($match) ? 'cup_match' : 'title_decider';
        }

        if ($this->isDerby($match)) {
            $importance += 20;
            $reasons[] = 'derby';
        }

        $luck = mt_rand(0, 15);
        $importance += $luck;

        return [
            'accepted' => $importance >= self::ACCEPT_THRESHOLD,
            'importance' => min(100, $importance),
            'reasons' => $reasons,
        ];
    }

    /**
     * Deterministic per club+season: does this men's club refuse to lend
     * its ground this season? ~30% of clubs are "difficult" — the rest
     * always say yes. Stable within a season so the player can't spam
     * the request until it flips.
     */
    public function isClubDifficult(string $club, string $season): bool
    {
        // PHP 32-bit safe: max 7 hex digits.
        $roll = hexdec(substr(md5('difficult|'.$club.'|'.$season), 0, 7)) % 100;

        return $roll < 30;
    }

    /**
     * Deterministic excuse for a difficult club (same club+season always
     * gives the same excuse).
     */
    public function deterministicExcuse(string $club, string $season): string
    {
        $excuses = [
            'excuse_pitch', 'excuse_concert', 'excuse_works', 'excuse_derby', 'excuse_board',
        ];
        $idx = hexdec(substr(md5('excuse|'.$club.'|'.$season), 0, 7)) % count($excuses);

        return $excuses[$idx];
    }

    /**
     * The owner names their price for this match. Returns the quote the
     * user sees before confirming (or the refusal).
     *
     * Pricing (0.3.9, Izan's rule): grounds WITHOUT a team are FREE; all
     * others are paid to the CITY COUNCIL (not the club).
     *
     * @return array{eligible: bool, accepted: bool, importance: int, reasons: list<string>, price: int, affiliated: bool, casa_invita: bool, stadium: array}
     */
    public function quoteForMatch(GameMatch $match, Game $game, array $stadium): array
    {
        $teamName = $game->team?->name ?? '';
        $affiliated = $this->isAffiliated($stadium, $teamName);

        $base = [
            'eligible' => true,
            'accepted' => false,
            'importance' => 0,
            'reasons' => [],
            'price' => 0,
            'affiliated' => $affiliated,
            'casa_invita' => false,
            'stadium' => $stadium,
        ];

        if (! $this->canRequest($game)) {
            $base['eligible'] = false;
            $base['reasons'] = ['limit_reached'];

            return $base;
        }

        if (! $this->hasEnoughAdvance($match, $game)) {
            $base['eligible'] = false;
            $base['reasons'] = ['too_late'];

            return $base;
        }

        // Teamless grounds (municipal, no club attached): FREE.
        if (($stadium['club'] ?? null) === null) {
            $base['accepted'] = true;
            $base['importance'] = 50;
            $base['price'] = 0;
            $base['reasons'][] = 'municipal_free';

            return $base;
        }

        // Some clubs just won't lend their ground this season: deterministic
        // excuse (same club+season => same answer).
        $season = (string) ($game->season ?? '');
        if ($this->isClubDifficult($stadium['club'], $season)) {
            $base['reasons'][] = $this->deterministicExcuse($stadium['club'], $season);

            return $base;
        }

        // Everyone else rents — paid to the CITY COUNCIL, never to the club.
        $base['accepted'] = true;
        $base['importance'] = 60;
        $base['price'] = (int) ($stadium['rental_price'] ?? 0);
        $base['reasons'][] = 'council_paid';

        return $base;
    }

    /**
     * Confirm a quoted request: charge the club (if priced) and move the
     * match to the stadium.
     *
     * @param array{price: int, stadium: array} $quote
     * @return array{ok: bool, error: string|null}
     */
    public function confirmQuote(GameMatch $match, Game $game, array $quote): array
    {
        $price = (int) ($quote['price'] ?? 0);
        $stadium = $quote['stadium'];

        if ($price > 0) {
            $investment = $game->currentInvestment;
            if (! $investment instanceof GameInvestment) {
                return ['ok' => false, 'error' => 'game.mens_stadium_no_budget'];
            }

            // Budgets and transactions are stored in cents.
            $priceCents = $price * 100;
            $available = $investment->transfer_budget - TransferOffer::committedBudget($game->id);
            if ($priceCents > $available) {
                return ['ok' => false, 'error' => 'game.mens_stadium_cant_afford'];
            }

            DB::transaction(function () use ($game, $investment, $match, $stadium, $price, $priceCents) {
                $investment->update(['transfer_budget' => $investment->transfer_budget - $priceCents]);

                FinancialTransaction::recordExpense(
                    gameId: $game->id,
                    category: FinancialTransaction::CATEGORY_VENUE_RENT,
                    amount: $priceCents,
                    description: __('game.mens_stadium_rent_tx_desc', [
                        'stadium' => $stadium['stadium'],
                        'opponent' => $match->awayTeam?->name ?? '',
                    ]),
                    transactionDate: $game->current_date->toDateString(),
                );

                $match->update([
                    'neutral_venue_name' => $stadium['stadium'],
                    'neutral_venue_capacity' => (int) $stadium['capacity'],
                ]);
            });

            return ['ok' => true, 'error' => null];
        }

        $match->update([
            'neutral_venue_name' => $stadium['stadium'],
            'neutral_venue_capacity' => (int) $stadium['capacity'],
        ]);

        return ['ok' => true, 'error' => null];
    }

    /**
     * Check if the request is made at least MIN_ADVANCE_DAYS before the match.
     */
    public function hasEnoughAdvance(GameMatch $match, Game $game): bool
    {
        if (! $match->scheduled_date || ! $game->current_date) {
            return true; // Can't verify, allow it
        }

        $matchDate = \Carbon\Carbon::parse($match->scheduled_date);
        $now = \Carbon\Carbon::parse($game->current_date);

        return $now->diffInDays($matchDate, false) >= self::MIN_ADVANCE_DAYS;
    }

    /**
     * Days remaining to request for a match (for UI display).
     */
    public function daysUntilDeadline(GameMatch $match, Game $game): int
    {
        if (! $match->scheduled_date || ! $game->current_date) {
            return 0;
        }

        $matchDate = \Carbon\Carbon::parse($match->scheduled_date);
        $now = \Carbon\Carbon::parse($game->current_date);
        $deadline = $matchDate->copy()->subDays(self::MIN_ADVANCE_DAYS);

        return (int) $now->diffInDays($deadline, false);
    }

    /**
     * Random excuse from the owner when they reject the request.
     */
    private function randomExcuse(): string
    {
        $excuses = [
            'excuse_laliga',      // Men's team has a league match that weekend
            'excuse_grass',       // Changing the pitch grass
            'excuse_concert',     // Stadium booked for a concert/event
            'excuse_maintenance', // Scheduled maintenance works
            'excuse_reserve',     // Reserve team playing there
        ];

        return $excuses[array_rand($excuses)];
    }

    private function isCupOrKnockout(GameMatch $match): bool
    {
        $compId = strtoupper($match->competition_id ?? '');

        return str_contains($compId, 'CUP')
            || str_contains($compId, 'UCL')
            || str_contains($compId, 'UWCL')
            || $match->cup_tie_id !== null;
    }

    private function isTitleDecider(GameMatch $match, Game $game): bool
    {
        // Last 5 league matchdays with the title race alive.
        if ($this->isCupOrKnockout($match)) {
            return false;
        }

        $totalMatchdays = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $match->competition_id)
            ->distinct()
            ->count('round_number');

        if ($totalMatchdays < 10 || ($match->round_number ?? 0) < $totalMatchdays - 5) {
            return false;
        }

        // Title race alive: home team within 9 points of the leader.
        // Standings are computed elsewhere; use a lightweight points check.
        $standings = $this->pointsTable($game, $match->competition_id);
        if (empty($standings)) {
            return false;
        }

        $leader = max($standings);
        $home = $standings[$match->home_team_id] ?? 0;

        return ($leader - $home) <= 9;
    }

    /**
     * @return array<string, int> team_id => points
     */
    private function pointsTable(Game $game, string $competitionId): array
    {
        $table = [];
        $matches = GameMatch::where('game_id', $game->id)
            ->where('competition_id', $competitionId)
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        foreach ($matches as $m) {
            $table[$m->home_team_id] ??= 0;
            $table[$m->away_team_id] ??= 0;
            if ($m->home_score > $m->away_score) {
                $table[$m->home_team_id] += 3;
            } elseif ($m->home_score < $m->away_score) {
                $table[$m->away_team_id] += 3;
            } else {
                $table[$m->home_team_id] += 1;
                $table[$m->away_team_id] += 1;
            }
        }

        return $table;
    }

    private function isDerby(GameMatch $match): bool
    {
        $home = $match->homeTeam;
        $away = $match->awayTeam;

        if (! $home || ! $away) {
            return false;
        }

        return ($home->country ?? null) !== null
            && $home->country === ($away->country ?? null);
    }

    private function loadStadiums(): void
    {
        if ($this->stadiumsByName !== null) {
            return;
        }

        $data = Cache::remember('mens_stadiums_v2', 86400, function () {
            $path = base_path('data/mens_stadiums.json');
            if (! is_file($path)) {
                return [];
            }

            $json = json_decode(file_get_contents($path), true);

            return $json['stadiums'] ?? [];
        });

        $this->stadiumsByName = [];
        $this->affiliatedMap = [];
        foreach ($data as $row) {
            $row['capacity'] = (int) ($row['capacity'] ?? 0);
            $row['rental_price'] = (int) ($row['rental_price'] ?? 0);
            // Shared grounds (San Siro: Milan + Inter, Olimpico: Roma +
            // Lazio) appear once per owner with their own price: the lookup
            // key is stadium|owner so each priced entry stays reachable.
            $row['key'] = $row['stadium'] . '|' . ($row['club'] ?? $row['owner'] ?? '');
            $this->stadiumsByName[$row['key']] = $row;
            // A men's ground can serve several of the entity's women's
            // teams: the primary womens_team plus any womens_teams aliases
            // (first team + B/C squads share the ground).
            foreach ([$row['womens_team'] ?? null, ...($row['womens_teams'] ?? [])] as $alias) {
                if (! empty($alias)) {
                    $this->affiliatedMap[$alias] = $row;
                }
            }
        }
    }
}

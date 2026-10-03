<?php

declare(strict_types=1);

namespace App\Modules\Stadium\Services;

use App\Models\FinancialTransaction;
use App\Models\Game;
use App\Models\GameInvestment;
use App\Models\GameMatch;
use App\Models\TransferOffer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Jugar en el estadio masculino": the user's women's club asks to play a
 * home match at a men's stadium (e.g. Valencia Femenino asking to play at
 * Mestalla, or renting La Cartuja from the city council).
 *
 * MODEL (0.3.9): the rental catalogue is open to any ground in the user's
 * own country. Grounds WITHOUT a team (municipal, e.g. La Cartuja) are
 * FREE; every other ground costs the FULL listed price, paid to the CITY
 * COUNCIL (never to the men's club). There is no "precio de la casa" and
 * no "casa invita" free nights: the affiliated-club discount (50%) and the
 * importance>=80 free nights were removed intentionally in 0.3.9 —
 * affiliated clubs pay the full listed price like everyone else.
 *
 * Some men's clubs refuse to lend their ground this season: ~30% are
 * "difficult" (deterministic per club+season via isClubDifficult()) and
 * answer with a deterministic excuse (deterministicExcuse()).
 *
 * Max 3 matches per season, requested at least 21 days in advance.
 */
class MensStadiumRequestService
{
    public const MAX_PER_SEASON = 3;

    /**
     * Minimum days in advance to request a men's stadium.
     * The owner needs time to organize logistics.
     */
    public const MIN_ADVANCE_DAYS = 21;

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

    /**
     * Rentals used this season. Counts ONLY matches flagged as men's-stadium
     * rentals: neutral cup finals (CupDrawService) also set
     * neutral_venue_name, but they must not eat the MAX_PER_SEASON quota.
     */
    public function usesThisSeason(Game $game): int
    {
        return GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('mens_stadium_rental', true)
            ->count();
    }

    public function canRequest(Game $game): bool
    {
        return $this->usesThisSeason($game) < self::MAX_PER_SEASON;
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
     * Deterministic excuse for a difficult club (same club+season+month
     * always gives the same excuse).
     *
     * Month-aware: the "decisive stretch" pitch excuse only makes sense in
     * March-May. In any other month (e.g. December) it would be nonsense,
     * so it is excluded from the pool.
     */
    public function deterministicExcuse(string $club, string $season, ?int $month = null): string
    {
        $excuses = [
            'excuse_pitch', 'excuse_concert', 'excuse_works', 'excuse_derby', 'excuse_board',
        ];

        if ($month !== null && ! in_array($month, [3, 4, 5], true)) {
            $excuses = array_values(array_diff($excuses, ['excuse_pitch']));
        }

        $idx = hexdec(substr(md5('excuse|'.$club.'|'.$season.'|'.($month ?? 0)), 0, 7)) % count($excuses);

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
        // excuse (same club+season+month => same answer).
        $season = (string) ($game->season ?? '');
        if ($this->isClubDifficult($stadium['club'], $season)) {
            $month = $game->current_date ? (int) \Carbon\Carbon::parse($game->current_date)->month : null;
            $base['reasons'][] = $this->deterministicExcuse($stadium['club'], $season, $month);

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
     * The per-season limit (MAX_PER_SEASON) is re-validated INSIDE the
     * transaction: the step-1 check in quoteForMatch() may be stale by the
     * time the user confirms, and parallel quotes could otherwise all pass
     * it and land 4 rentals in the same season. The budget is likewise
     * re-read under a row lock so two concurrent confirmations can't spend
     * the same euros twice (TOCTOU).
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

            try {
                DB::transaction(function () use ($game, $investment, $match, $stadium, $price, $priceCents) {
                    $locked = GameInvestment::whereKey($investment->id)->lockForUpdate()->first();
                    if ($locked === null) {
                        throw new \DomainException('game.mens_stadium_no_budget');
                    }

                    if (! $this->canRequest($game)) {
                        throw new \DomainException('game.mens_stadium_limit_reached');
                    }

                    $freshAvailable = $locked->transfer_budget - TransferOffer::committedBudget($game->id);
                    if ($priceCents > $freshAvailable) {
                        throw new \DomainException('game.mens_stadium_cant_afford');
                    }

                    $locked->update(['transfer_budget' => $locked->transfer_budget - $priceCents]);

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
                        'mens_stadium_rental' => true,
                    ]);
                });
            } catch (\DomainException $e) {
                $key = $e->getMessage();

                return ['ok' => false, 'error' => $key === 'game.mens_stadium_limit_reached'
                    ? __('game.mens_stadium_limit_reached', ['max' => self::MAX_PER_SEASON])
                    : $key];
            }

            return ['ok' => true, 'error' => null];
        }

        // Free (municipal) rentals still consume the per-season quota: the
        // step-1 check may be stale by confirm time.
        if (! $this->canRequest($game)) {
            return [
                'ok' => false,
                'error' => __('game.mens_stadium_limit_reached', ['max' => self::MAX_PER_SEASON]),
            ];
        }

        $match->update([
            'neutral_venue_name' => $stadium['stadium'],
            'neutral_venue_capacity' => (int) $stadium['capacity'],
            'mens_stadium_rental' => true,
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

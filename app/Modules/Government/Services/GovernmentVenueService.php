<?php

declare(strict_types=1);

namespace App\Modules\Government\Services;

use App\Models\Game;
use App\Models\GameMatch;
use App\Models\GameNotification;
use App\Modules\Notification\Services\NotificationService;
use Illuminate\Support\Facades\DB;

/**
 * Government venue offers (F5, 0.3.9): regional/national governments
 * OCCASIONALLY offer a national team a real stadium to play at, via
 * in-game mail. The player accepts (picks one of the offered grounds)
 * or rejects.
 *
 * Accepted venues are free and apply to the team's next home match.
 */
class GovernmentVenueService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Maybe send a venue offer. Call after advancing a national-team
     * matchday. Returns the notification, or null if no offer was made.
     */
    public function maybeOffer(Game $game): ?GameNotification
    {
        if (($game->team?->type ?? null) !== 'national') {
            return null;
        }

        $country = $game->team?->country;
        $governments = config('government_venues.'.$country, []);
        if ($governments === []) {
            return null;
        }

        // Needs an upcoming home match to stage.
        $nextHome = GameMatch::where('game_id', $game->id)
            ->where('home_team_id', $game->team_id)
            ->where('played', false)
            ->whereNull('neutral_venue_name')
            ->orderBy('scheduled_date')
            ->first();
        if ($nextHome === null) {
            return null;
        }

        // One pending offer at a time.
        $pending = GameNotification::where('game_id', $game->id)
            ->ofType(GameNotification::TYPE_GOVERNMENT_VENUE_OFFER)
            ->whereNull('read_at')
            ->exists();
        if ($pending) {
            return null;
        }

        // Occasional: ~25% per check.
        if (mt_rand(1, 100) > 25) {
            return null;
        }

        $gov = $governments[array_rand($governments)];
        $stadiums = array_values(array_filter(
            $gov['stadiums'] ?? [],
            fn (string $name) => $this->stadiumExists($name)
        ));
        if ($stadiums === []) {
            return null;
        }

        return $this->notifications->create(
            $game,
            GameNotification::TYPE_GOVERNMENT_VENUE_OFFER,
            __('game.gov_venue_offer_title', ['government' => $gov['government']]),
            __('game.gov_venue_offer_body', [
                'government' => $gov['government'],
                'stadiums' => implode(', ', $stadiums),
                'opponent' => $nextHome->awayTeam?->name ?? '',
            ]),
            GameNotification::PRIORITY_INFO,
            [
                'government' => $gov['government'],
                'stadiums' => $stadiums,
                'match_id' => $nextHome->id,
                'status' => 'pending',
            ],
            'landmark',
        );
    }

    /**
     * Accept the offer for a specific stadium: the next home match moves
     * there, free of charge (the government foots the bill).
     *
     * The notification row is locked inside a transaction so two concurrent
     * accepts can't both pass the 'pending' check (double-accept race).
     *
     * @return array{ok: bool, error: string|null}
     */
    public function accept(GameNotification $notification, string $stadiumName): array
    {
        return DB::transaction(function () use ($notification, $stadiumName) {
            $locked = GameNotification::whereKey($notification->id)->lockForUpdate()->first();
            if ($locked === null) {
                return ['ok' => false, 'error' => 'game.gov_venue_invalid'];
            }

            $meta = $locked->metadata ?? [];
            if (($meta['status'] ?? null) !== 'pending') {
                return ['ok' => false, 'error' => 'game.gov_venue_already_answered'];
            }

            if (! in_array($stadiumName, $meta['stadiums'] ?? [], true)) {
                return ['ok' => false, 'error' => 'game.gov_venue_invalid_stadium'];
            }

            $match = GameMatch::where('game_id', $locked->game_id)
                ->where('id', $meta['match_id'] ?? null)
                ->where('played', false)
                ->first();
            if ($match === null) {
                return ['ok' => false, 'error' => 'game.gov_venue_invalid'];
            }

            $stadium = $this->stadiumByName($stadiumName);

            $match->update([
                'neutral_venue_name' => $stadiumName,
                'neutral_venue_capacity' => $stadium['capacity'] ?? null,
                'government_sponsored' => true,
            ]);

            $meta['status'] = 'accepted';
            $meta['chosen_stadium'] = $stadiumName;
            $locked->update(['metadata' => $meta, 'read_at' => now()]);

            return ['ok' => true, 'error' => null];
        });
    }

    public function reject(GameNotification $notification): void
    {
        $meta = $notification->metadata ?? [];
        $meta['status'] = 'rejected';
        $notification->update(['metadata' => $meta, 'read_at' => now()]);
    }

    private function stadiumExists(string $name): bool
    {
        return $this->stadiumByName($name) !== null;
    }

    private function stadiumByName(string $name): ?array
    {
        $data = \Illuminate\Support\Facades\Cache::remember(
            'mens_stadiums_v2',
            86400,
            function () {
                $path = base_path('data/mens_stadiums.json');
                if (! is_file($path)) {
                    return [];
                }

                return json_decode(file_get_contents($path), true)['stadiums'] ?? [];
            }
        );

        foreach ($data as $row) {
            if (($row['stadium'] ?? null) === $name) {
                return $row;
            }
        }

        return null;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Modules\Stadium\Services\NationalVenueRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NationalVenueRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_federation_stadiums_only_own_country(): void
    {
        $service = app(NationalVenueRequestService::class);
        $spain = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);

        $stadiums = $service->federationStadiums($spain);

        $this->assertNotEmpty($stadiums);
        foreach ($stadiums as $s) {
            $this->assertSame('Spain', $s['country']);
        }
    }

    public function test_default_national_stadium_is_la_cartuja_for_spain(): void
    {
        $service = app(NationalVenueRequestService::class);
        $spain = Team::factory()->create(['name' => 'Spain', 'type' => 'national']);

        $stadiums = $service->federationStadiums($spain);
        $default = $service->defaultNationalStadium($stadiums, $spain);

        $this->assertNotNull($default);
        $this->assertSame('Estadio La Cartuja', $default['stadium']);
    }

    public function test_club_stadiums_never_duplicate_teams(): void
    {
        // Two rows, same club name (stale duplicate data).
        Team::factory()->create(['name' => 'Hércules CF', 'type' => 'club', 'stadium_name' => 'José Rico Pérez', 'stadium_seats' => 30000]);
        Team::factory()->create(['name' => 'Hércules CF', 'type' => 'club', 'stadium_name' => 'José Rico Pérez', 'stadium_seats' => 30000]);

        $service = app(NationalVenueRequestService::class);
        $names = $service->clubStadiums()->pluck('team_name');

        $this->assertSame(1, $names->filter(fn ($n) => $n === 'Hércules CF')->count());
    }
}

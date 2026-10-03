<?php

namespace Tests\Feature\QaMediumFixes;

use App\Models\Game;
use App\Models\Team;
use App\Models\User;
use App\Modules\Media\Services\NationalSocialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión M13: Francia usaba la cuenta masculina @equipedefrance en
 * "Redes de la selección".
 *
 * Bug original: config/national_social_handles.php mapeaba France a
 * @equipedefrance con 'federation' => true ("sin cuenta femenina
 * separada"). La cuenta femenina sí existe: @equipedefrancef (verificado
 * por el QA en la bio oficial de @equipedefrance y en @FrenchTeam).
 * (qa/bugs/agent-14.md)
 *
 * Fix: France → @equipedefrancef (X e Instagram), sin el flag
 * 'federation' (ya es cuenta femenina propia: no debe salir la nota
 * ámbar "Cuenta de la federación").
 */
class M13FranceWomensHandleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Francia usa @equipedefrancef y no se marca como cuenta de federación.
     *
     * Bug original: devolvía @equipedefrance con federation=true.
     */
    public function test_france_uses_womens_account_not_mens(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'France', 'type' => 'national', 'country' => 'FR']);
        $game = Game::factory()->create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'country' => 'FR',
        ])->fresh('team');

        $service = app(NationalSocialService::class);

        $this->assertSame('@equipedefrancef', $service->nationalHandle($game));
        $this->assertSame('@equipedefrancef', $service->instagramHandle($game));
        $this->assertFalse(
            $service->isFederationAccount($game),
            'al ser cuenta femenina propia no debe mostrar la nota de federación'
        );
    }
}

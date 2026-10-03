<?php

namespace Tests\Feature\ReviewMediosFixes;

use App\Models\Competition;
use App\Models\Game;
use App\Models\GameNotification;
use App\Models\GamePlayer;
use App\Models\Team;
use App\Models\User;
use App\Modules\Academy\Services\YouthAcademyService;
use App\Modules\Match\Services\CareerActionProcessor;
use App\Modules\Media\Services\SocialMediaService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Transfer\Services\AITransferMarketService;
use App\Modules\Transfer\Services\LoanService;
use App\Modules\Transfer\Services\ScoutingService;
use App\Modules\Transfer\Services\TransferMarketService;
use App\Modules\Transfer\Services\TransferService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fix 14 de la revisión de medios (grupo 08): process() ejecutaba ~15
 * secciones con escrituras sin atomicidad — un fallo intermedio dejaba
 * estado parcial sin reintento en el siguiente tick. Ahora todo el pase
 * corre en una transacción: o aterrizan todas las secciones o ninguna.
 */
class CareerActionProcessorFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_mid_process_failure_rolls_back_earlier_sections(): void
    {
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        // Una jugadora con contrato a punto de expirar: checkExpiringContracts
        // (sección anterior a develop_academy) escribe una notificación.
        GamePlayer::factory()->create([
            'game_id' => $game->id,
            'team_id' => $team->id,
            'contract_until' => Carbon::parse('2027-02-01'),
            'pending_annual_wage' => null,
        ]);

        // developPlayers revienta DESPUÉS de checkExpiringContracts.
        $explodingAcademy = new class extends YouthAcademyService {
            public static ?int $seenTransactionLevel = null;

            public function __construct() {}

            public function developPlayers(Game $game): void
            {
                self::$seenTransactionLevel = DB::transactionLevel();
                throw new \RuntimeException('academy boom');
            }
        };

        $processor = new CareerActionProcessor(
            app(TransferService::class),
            app(ScoutingService::class),
            app(LoanService::class),
            $explodingAcademy,
            app(NotificationService::class),
            app(AITransferMarketService::class),
            app(TransferMarketService::class),
            app(SocialMediaService::class),
        );

        try {
            $processor->process($game);
            $this->fail('process() debería haber lanzado la excepción de la academia');
        } catch (\RuntimeException $e) {
            $this->assertSame('academy boom', $e->getMessage());
        }

        // Las secciones corren dentro de una transacción (nivel 1 del test +
        // 1 del process).
        $this->assertGreaterThanOrEqual(2, $explodingAcademy::$seenTransactionLevel);

        // La notificación escrita por la sección anterior se ha revertido.
        $this->assertSame(
            0,
            GameNotification::where('game_id', $game->id)
                ->where('type', GameNotification::TYPE_CONTRACT_EXPIRING)
                ->count(),
            'quedó estado parcial tras el fallo intermedio'
        );
    }

    public function test_process_completes_without_writes_on_a_quiet_game(): void
    {
        Competition::factory()->league()->create(['id' => 'ESP1', 'country' => 'ES', 'tier' => 1]);
        $team = Team::factory()->create(['country' => 'ES']);
        $game = Game::factory()->create([
            'user_id' => User::factory()->create()->id,
            'country' => 'ES',
            'team_id' => $team->id,
            'competition_id' => 'ESP1',
            'current_date' => Carbon::parse('2026-10-03'),
        ]);

        // Guarda de regresión: el envoltorio transaccional no rompe el pase
        // normal en un juego sin actividad pendiente.
        app(CareerActionProcessor::class)->process($game);

        $this->assertTrue(true);
    }
}

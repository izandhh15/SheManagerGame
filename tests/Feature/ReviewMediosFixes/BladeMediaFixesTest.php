<?php

namespace Tests\Feature\ReviewMediosFixes;

use PHPUnit\Framework\TestCase;

/**
 * M-review fix 5/6 (grupos 17, 18, 20 + llamante de simulate-tournament,
 * TRIAGE-B): regresión sobre los cambios de vistas Blade y del llamante
 * del endpoint de simulación de torneos.
 *
 * Son aserciones sobre el fuente/compilado de las plantillas: documentan
 * cada fix y evitan que una edición futura reintroduzca el defecto.
 */
class BladeMediaFixesTest extends TestCase
{
    private function view(string $relative): string
    {
        return file_get_contents(__DIR__.'/../../../resources/views/'.$relative);
    }

    // -- Fix 1: game-breadcrumb usaba Player::find() con un UUID de GamePlayer --

    public function test_breadcrumb_looks_up_game_player_not_player(): void
    {
        $src = $this->view('components/game-breadcrumb.blade.php');

        $this->assertStringContainsString('GamePlayer::', $src);
        $this->assertStringNotContainsString('Player::find(', $src);
        $this->assertStringContainsString("where('game_id', \$game->id)", $src);
    }

    // -- Fix 2: (int) sobre competitionId con Competition de clave string --

    public function test_breadcrumb_does_not_cast_competition_id_to_int(): void
    {
        $src = $this->view('components/game-breadcrumb.blade.php');

        $this->assertStringNotContainsString('(int) request()->route(', $src);
        $this->assertStringContainsString(
            "firstWhere('id', request()->route('competitionId'))",
            $src
        );
    }

    // -- Fix 3: fixture-row ocultaba el indicador H/A solo para WC2026 --

    public function test_fixture_row_uses_is_neutral_venue(): void
    {
        $src = $this->view('components/fixture-row.blade.php');

        $this->assertStringContainsString('$match->isNeutralVenue()', $src);
        $this->assertStringNotContainsString("competition_id !== 'WC2026'", $src);
    }

    // -- Fix 6: bg-accent-600 / hover:bg-accent-500 no existen en el tema --

    public function test_stadium_view_uses_real_theme_classes(): void
    {
        $src = $this->view('club/stadium.blade.php');

        $this->assertStringNotContainsString('bg-accent-600', $src);
        $this->assertStringNotContainsString('hover:bg-accent-500', $src);
        $this->assertStringContainsString('bg-accent-gold', $src);
    }

    // -- Fix 7: bandera 🇪🇸 hardcodeada en el picker de selecciones --

    public function test_squad_picker_card_has_no_hardcoded_flag(): void
    {
        $src = $this->view('partials/squad-picker-card.blade.php');

        $this->assertStringNotContainsString('🇪🇸', $src);
        $this->assertStringContainsString('p.nt_flag', $src);
    }

    public function test_squad_picker_payload_carries_nt_flag(): void
    {
        $src = $this->view('national-squad-picker.blade.php');

        $this->assertStringContainsString("'nt_flag' => \$ntFlag", $src);
    }

    // -- Fix 8: max-w-5xl y max-w-7xl en conflicto en modo blocking --

    public function test_squad_registration_has_single_max_width(): void
    {
        $src = $this->view('squad-registration.blade.php');

        // El ternario decide el ancho; no puede haber un max-w-7xl literal
        // fuera de él que compita con el max-w-5xl del modo blocking.
        $this->assertDoesNotMatchRegularExpression(
            "/\}\}\s*max-w-7xl/",
            $src,
            'squad-registration.blade.php emite dos max-w-* en conflicto'
        );
    }

    // -- Fix 9: apóstrofo francés rompía el x-text de payment_intro --

    public function test_payment_intro_uses_js_directive_not_raw_interpolation(): void
    {
        $src = $this->view('partials/player-detail.blade.php');

        // El patrón seguro: @js() (hex-escapes) en lugar de interpolar el
        // texto con apóstrofos dentro de un string JS entre comillas simples.
        $this->assertStringContainsString(
            "@js(__('termination.payment_intro'",
            $src
        );
        $this->assertStringNotContainsString(
            "'{{ __('termination.payment_intro'",
            $src
        );
    }

    // -- Fix 13: el llamante de simulate-tournament (ahora POST) --

    public function test_show_game_drives_tournament_simulation_via_post_view(): void
    {
        $src = file_get_contents(__DIR__.'/../../../app/Http/Views/ShowGame.php');

        // Un redirect GET a una ruta POST daría 405: se renderiza la vista
        // que conduce la simulación por chunks con POST+CSRF.
        $this->assertStringNotContainsString(
            "redirect()->route('game.simulate-tournament'",
            $src
        );
        $this->assertStringContainsString("view('tournament-simulation'", $src);
    }

    public function test_tournament_simulation_view_posts_with_csrf(): void
    {
        $src = $this->view('tournament-simulation.blade.php');

        $this->assertStringContainsString("route('game.simulate-tournament'", $src);
        $this->assertStringContainsString("method: 'POST'", $src);
        $this->assertStringContainsString('X-CSRF-TOKEN', $src);
        $this->assertStringContainsString('data.done', $src);
        $this->assertStringContainsString('window.location = data.redirect', $src);
    }
}

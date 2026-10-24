<?php

namespace App\View\Components;

use App\Models\Game;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Next-gen game shell (fase 1, rama next-gen-menus).
 *
 * Same component pattern as AppLayout (class -> layouts/*.blade.php), but
 * with a desktop sidebar + mobile bottom bar instead of the old top
 * header + dropdown nav. Used as <x-game-nextgen-layout :game="$game">.
 *
 * $game is nullable: without a game (e.g. the dashboard, "Mis partidas")
 * the sidebar shows the global nav only. No game logic lives here — the
 * layout only links to EXISTING routes; every target screen still runs
 * its own view class with the old layout until later phases migrate it.
 *
 * Screens migrated to this shell so far (fase 1):
 *   - dashboard (Mis partidas) and game-hub-nextgen (nuevo menú del juego)
 * Everything else keeps <x-app-layout> — see the migration note at the
 * top of resources/views/layouts/game-nextgen.blade.php.
 */
class GameNextgenLayout extends Component
{
    public function __construct(
        public ?Game $game = null,
    ) {}

    public function render(): View
    {
        return view('layouts.game-nextgen');
    }
}

<?php
// TEMPORAL: diagnosticar y aplicar 000039 paso a paso + 000041/000042. Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

$out = [];
function step($label, $fn) {
    global $out;
    try {
        $r = $fn();
        $out[] = "OK $label => " . (is_string($r) ? $r : json_encode($r));
        return true;
    } catch (Throwable $e) {
        $out[] = "FAIL $label => [" . $e->getCode() . '] ' . substr($e->getMessage(), 0, 300);
        return false;
    }
}

try {
    // Columnas de press_statements
    step('cols game_id', fn() => Schema::hasColumn('press_statements', 'game_id') ? 'yes' : 'NO');
    step('cols match_id', fn() => Schema::hasColumn('press_statements', 'match_id') ? 'yes' : 'NO');
    step('cols created_at', fn() => Schema::hasColumn('press_statements', 'created_at') ? 'yes' : 'NO');
    step('total rows', fn() => DB::table('press_statements')->count());
    step('dupes count', fn() => DB::selectOne(<<<'SQL'
        SELECT count(*) AS c FROM press_statements ps
        WHERE ps.match_id IS NOT NULL AND EXISTS (
          SELECT 1 FROM press_statements ps2
          WHERE ps2.match_id IS NOT NULL
            AND ps2.game_id = ps.game_id AND ps2.match_id = ps.match_id
            AND (ps2.created_at, ps2.id) < (ps.created_at, ps.id)
        )
        SQL)->c);

    // DELETE de duplicados (igual que la migración)
    if (step('delete dupes', fn() => DB::delete(<<<'SQL'
        DELETE FROM press_statements ps
        USING press_statements ps2
        WHERE ps.match_id IS NOT NULL
          AND ps2.match_id IS NOT NULL
          AND ps.game_id = ps2.game_id
          AND ps.match_id = ps2.match_id
          AND (ps2.created_at, ps2.id) < (ps.created_at, ps.id)
        SQL) . ' rows')) {
        // Constraint único
        step('add unique', function () {
            Schema::table('press_statements', function (Blueprint $t) {
                $t->unique(['game_id', 'match_id'], 'press_statements_game_match_unique');
            });
            return 'added';
        });
    }

    // game_date
    step('add game_date', function () {
        if (Schema::hasColumn('social_posts', 'game_date')) return 'exists';
        Schema::table('social_posts', fn(Blueprint $t) => $t->date('game_date')->nullable());
        return 'added';
    });

    // 000041
    step('add game_player_id', function () {
        if (Schema::hasColumn('social_posts', 'game_player_id')) return 'exists';
        Schema::table('social_posts', function (Blueprint $t) {
            $t->uuid('game_player_id')->nullable();
            $t->index('game_player_id');
        });
        return 'added';
    });

    // 000042
    step('add match_events_archive', function () {
        if (Schema::hasColumn('season_archives', 'match_events_archive')) return 'exists';
        Schema::table('season_archives', fn(Blueprint $t) => $t->json('match_events_archive')->nullable());
        return 'added';
    });

    // Ledger
    foreach ([
        '2026_10_03_000039_qa_medios_feed_fixes',
        '2026_10_03_000041_qa_medios_injury_statement_player',
        '2026_10_03_000042_add_match_events_archive_to_season_archives',
    ] as $name) {
        if (!DB::table('migrations')->where('migration', $name)->exists()) {
            DB::table('migrations')->insert(['migration' => $name, 'batch' => 3]);
            $out[] = "ledger: $name recorded";
        } else { $out[] = "ledger: $name already recorded"; }
    }
    $out[] = 'ledger_count=' . DB::table('migrations')->count();
} catch (Throwable $e) {
    $out[] = 'FATAL: [' . $e->getCode() . '] ' . substr($e->getMessage(), 0, 300);
}
echo implode("\n", $out) . "\n";

<?php
// TEMPORAL: aplicar 000036..000038 con guards (la BD trae image_url sin migración). Borrar tras usar.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

$out = [];
try {
    $applied = [];

    // --- 000036: social_posts.image_url + post_kind ---
    if (!Schema::hasColumn('social_posts', 'image_url')) {
        Schema::table('social_posts', fn(Blueprint $t) => $t->string('image_url', 500)->nullable());
        $applied[] = 'social_posts.image_url added';
    } else { $applied[] = 'social_posts.image_url already exists, skipped'; }
    if (!Schema::hasColumn('social_posts', 'post_kind')) {
        Schema::table('social_posts', fn(Blueprint $t) => $t->string('post_kind', 50)->nullable());
        $applied[] = 'social_posts.post_kind added';
    } else { $applied[] = 'social_posts.post_kind already exists, skipped'; }

    // --- 000037: friendships federation columns ---
    $cols37 = [
        'is_federated' => fn(Blueprint $t) => $t->boolean('is_federated')->default(false),
        'is_remote_sender' => fn(Blueprint $t) => $t->boolean('is_remote_sender')->default(false),
        'remote_user_id' => fn(Blueprint $t) => $t->unsignedBigInteger('remote_user_id')->nullable(),
        'remote_username' => fn(Blueprint $t) => $t->string('remote_username', 255)->nullable(),
        'remote_club' => fn(Blueprint $t) => $t->string('remote_club', 255)->nullable(),
        'remote_instance' => fn(Blueprint $t) => $t->string('remote_instance', 255)->nullable(),
        'remote_request_uuid' => fn(Blueprint $t) => $t->uuid('remote_request_uuid')->nullable(),
    ];
    foreach ($cols37 as $col => $def) {
        if (!Schema::hasColumn('friendships', $col)) {
            Schema::table('friendships', $def);
            $applied[] = "friendships.$col added";
        } else { $applied[] = "friendships.$col already exists, skipped"; }
    }
    DB::statement('CREATE INDEX IF NOT EXISTS friendships_is_federated_index ON friendships (is_federated)');
    DB::statement('CREATE INDEX IF NOT EXISTS friendships_remote_username_remote_instance_index ON friendships (remote_username, remote_instance)');
    $applied[] = 'indexes ensured';
    $nullable = DB::selectOne("SELECT is_nullable FROM information_schema.columns WHERE table_name='friendships' AND column_name='friend_id'");
    if ($nullable && $nullable->is_nullable === 'NO') {
        DB::statement('ALTER TABLE friendships ALTER COLUMN friend_id DROP NOT NULL');
        $applied[] = 'friendships.friend_id now nullable';
    } else { $applied[] = 'friendships.friend_id already nullable, skipped'; }

    // --- 000038: academy_players.growth_progress ---
    if (!Schema::hasColumn('academy_players', 'growth_progress')) {
        Schema::table('academy_players', fn(Blueprint $t) => $t->float('growth_progress')->default(0));
        $applied[] = 'academy_players.growth_progress added';
    } else { $applied[] = 'academy_players.growth_progress already exists, skipped'; }

    // --- Registrar en el ledger ---
    foreach ([
        '2026_10_03_000036_add_poster_to_social_posts',
        '2026_10_03_000037_add_federation_to_friendships_table',
        '2026_10_03_000038_add_growth_progress_to_academy_players_table',
    ] as $name) {
        $exists = DB::table('migrations')->where('migration', $name)->exists();
        if (!$exists) {
            DB::table('migrations')->insert(['migration' => $name, 'batch' => 2]);
            $applied[] = "ledger: $name recorded";
        } else { $applied[] = "ledger: $name already recorded"; }
    }

    $out[] = 'ledger_count=' . DB::table('migrations')->count();
    $out[] = 'final poster_check image_url=' . (Schema::hasColumn('social_posts', 'image_url') ? 1 : 0)
        . ' post_kind=' . (Schema::hasColumn('social_posts', 'post_kind') ? 1 : 0)
        . ' is_federated=' . (Schema::hasColumn('friendships', 'is_federated') ? 1 : 0)
        . ' growth_progress=' . (Schema::hasColumn('academy_players', 'growth_progress') ? 1 : 0);
    $out = array_merge($out, $applied);
} catch (Throwable $e) {
    $out[] = 'ERROR: ' . $e->getMessage();
}
echo implode("\n", $out) . "\n";

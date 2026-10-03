<?php

// TEMPORARY: DB migration + import for Wasmer -> Neon move. DELETE AFTER MIGRATION.

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/_db-migrate/{token}/{step?}', function (string $token, ?string $step = null) {
    if (! hash_equals(env('DB_EXPORT_TOKEN', 'nope'), $token)) {
        abort(404);
    }

    try {
        $params = ['--force' => true];
        if ($step === 'one') {
            $params['--step'] = true;
        }
        Artisan::call('migrate', $params);
        return response()->json([
            'ok' => true,
            'output' => Artisan::output(),
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::post('/_db-import/{token}', function (string $token, \Illuminate\Http\Request $request) {
    if (! hash_equals(env('DB_EXPORT_TOKEN', 'nope'), $token)) {
        abort(404);
    }

    $sql = $request->getContent();
    if (empty($sql)) {
        return response()->json(['ok' => false, 'error' => 'empty body'], 400);
    }

    try {
        DB::unprepared($sql);
        return response()->json(['ok' => true, 'bytes' => strlen($sql)]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

Route::get('/_db-sequences/{token}', function (string $token) {
    if (! hash_equals(env('DB_EXPORT_TOKEN', 'nope'), $token)) {
        abort(404);
    }

    try {
        $pdo = DB::connection()->getPdo();
        $seqMap = $pdo->query(
            "SELECT s.relname AS seq, t.relname AS tbl, a.attname AS col
             FROM pg_class s
             JOIN pg_depend d ON d.objid = s.oid AND d.deptype = 'a'
             JOIN pg_class t ON t.oid = d.refobjid
             JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid
             WHERE s.relkind = 'S' AND s.relnamespace = 'public'::regnamespace"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($seqMap as $m) {
            $seq = str_replace('"', '""', $m['seq']);
            $tbl = str_replace('"', '""', $m['tbl']);
            $col = str_replace('"', '""', $m['col']);
            $pdo->exec("SELECT setval('\"{$seq}\"', COALESCE((SELECT max(\"{$col}\") FROM \"{$tbl}\"), 1))");
        }
        return response()->json(['ok' => true, 'sequences' => count($seqMap)]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

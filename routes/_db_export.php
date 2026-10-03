<?php

// TEMPORARY: full DB export for migration Wasmer -> Neon. DELETE AFTER MIGRATION.
// Protected by token. Streams tables as INSERT statements.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/_db-export/{token}', function (string $token) {
    if (! hash_equals(env('DB_EXPORT_TOKEN', 'nope'), $token)) {
        abort(404);
    }

    $pdo = DB::connection()->getPdo();

    $tables = $pdo->query(
        "SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"
    )->fetchAll(PDO::FETCH_COLUMN);

    // Order matters for FKs: dump in dependency-safe order is complex;
    // Neon import will run with session_replication_role = replica to skip FK checks.
    return response()->stream(function () use ($pdo, $tables) {
        echo "-- SheManager DB export " . date('c') . "\n";
        echo "SET session_replication_role = 'replica';\n\n";

        foreach ($tables as $table) {
            $count = (int) $pdo->query("SELECT count(*) FROM \"{$table}\"")->fetchColumn();
            echo "-- Table {$table}: {$count} rows\n";
            if ($count === 0) {
                continue;
            }

            $cols = $pdo->query(
                "SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=" . $pdo->quote($table) . " ORDER BY ordinal_position"
            )->fetchAll(PDO::FETCH_COLUMN);
            $colList = implode(',', array_map(fn ($c) => '"' . str_replace('"', '""', $c) . '"', $cols));

            $stmt = $pdo->query("SELECT * FROM \"{$table}\"");
            $batch = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null) {
                        $vals[] = 'NULL';
                    } elseif (is_resource($v)) {
                        $data = stream_get_contents($v);
                        $vals[] = "'\\x" . bin2hex($data) . "'";
                    } elseif (is_bool($v)) {
                        $vals[] = $v ? 'TRUE' : 'FALSE';
                    } else {
                        $vals[] = $pdo->quote((string) $v);
                    }
                }
                $batch[] = '(' . implode(',', $vals) . ')';
                if (count($batch) >= 500) {
                    echo "INSERT INTO \"{$table}\" ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
                    $batch = [];
                }
            }
            if ($batch) {
                echo "INSERT INTO \"{$table}\" ({$colList}) VALUES\n" . implode(",\n", $batch) . ";\n";
            }
            echo "\n";
            flush();
        }

        // Reset sequences to max(pk) for serial columns
        echo "-- Reset sequences\n";
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
            echo "SELECT setval('\"{$seq}\"', COALESCE((SELECT max(\"{$col}\") FROM \"{$tbl}\"), 1));\n";
        }
        echo "SET session_replication_role = 'origin';\n";
    }, 200, [
        'Content-Type' => 'application/sql',
        'Content-Disposition' => 'attachment; filename="shemanager-export.sql"',
        'X-Accel-Buffering' => 'no',
    ]);
});

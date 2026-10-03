<?php

// TEMPORARY: export from legacy Wasmer DB. DELETE AFTER MIGRATION.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/_legacy-export/{token}/{table}', function (string $token, string $table) {
    if (! hash_equals(env('DB_EXPORT_TOKEN', 'nope'), $token)) {
        abort(404);
    }

    try {
        $pdo = DB::connection('legacy')->getPdo();

        $count = (int) $pdo->query("SELECT count(*) FROM \"{$table}\"")->fetchColumn();

        $cols = $pdo->query(
            "SELECT column_name FROM information_schema.columns WHERE table_schema='public' AND table_name=" . $pdo->quote($table) . " ORDER BY ordinal_position"
        )->fetchAll(PDO::FETCH_COLUMN);
        $colList = implode(',', array_map(fn ($c) => '"' . str_replace('"', '""', $c) . '"', $cols));

        return response()->stream(function () use ($pdo, $table, $colList, $count) {
            echo "-- Table {$table}: {$count} rows\n";
            if ($count === 0) {
                return;
            }

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
            flush();
        }, 200, [
            'Content-Type' => 'application/sql',
            'X-Accel-Buffering' => 'no',
        ]);
    } catch (\Throwable $e) {
        return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
    }
});

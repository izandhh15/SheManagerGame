<?php

// TEMPORARY: diagnostics

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/_db-diag/{token}', function (string $token) {
    if (! hash_equals(env('DB_EXPORT_TOKEN', 'nope'), $token)) {
        abort(404);
    }

    try {
        $pdo = DB::connection()->getPdo();
        
        // Check migrations table
        $migrations = [];
        try {
            $migrations = $pdo->query("SELECT migration FROM migrations ORDER BY batch, migration")->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            $migrations = ['error: ' . $e->getMessage()];
        }
        
        // Check users table constraints
        $constraints = [];
        try {
            $constraints = $pdo->query(
                "SELECT conname FROM pg_constraint WHERE conrelid = 'users'::regclass"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            $constraints = ['error: ' . $e->getMessage()];
        }
        
        // Try the failing statement directly
        $directError = null;
        try {
            $pdo->exec('ALTER TABLE "users" ADD CONSTRAINT "users_email_unique" UNIQUE ("email")');
        } catch (\Throwable $e) {
            $directError = $e->getMessage();
        }

        // Try creating users table from scratch to see the real error
        $createError = null;
        try {
            $pdo->exec('DROP TABLE IF EXISTS "users"');
            $pdo->exec('CREATE TABLE "users" ("id" bigserial PRIMARY KEY, "name" varchar(255) NOT NULL, "email" varchar(255) NOT NULL)');
            $pdo->exec('ALTER TABLE "users" ADD CONSTRAINT "users_email_unique" UNIQUE ("email")');
            $pdo->exec('DROP TABLE "users"');
        } catch (\Throwable $e) {
            $createError = $e->getMessage();
        }
        
        return response()->json([
            'migrations_run' => count($migrations),
            'last_migrations' => array_slice($migrations, -5),
            'users_constraints' => $constraints,
            'direct_alter_error' => $directError,
            'create_test_error' => $createError,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

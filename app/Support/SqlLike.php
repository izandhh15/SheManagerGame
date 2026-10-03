<?php

namespace App\Support;

/**
 * Helpers for building LIKE patterns from user input.
 */
final class SqlLike
{
    /**
     * Escape the LIKE wildcards (% and _) plus the escape character
     * itself, so user input is matched literally instead of acting as
     * a wildcard. PostgreSQL uses the backslash as the default LIKE
     * escape character, so no explicit ESCAPE clause is needed.
     */
    public static function escape(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}

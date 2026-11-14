<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Pequeñas diferencias de sintaxis entre PostgreSQL (oficial del proyecto) y
 * MySQL (modo de desarrollo local sin Postgres instalado). Cada método elige
 * la sintaxis según el driver activo; bajo pgsql el comportamiento es idéntico
 * al de antes.
 */
final class Sql
{
    public static function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public static function likeOperator(): string
    {
        return self::isPgsql() ? 'ilike' : 'like';
    }

    public static function concatWithSpace(string $a, string $b): string
    {
        return self::isPgsql() ? "({$a} || ' ' || {$b})" : "CONCAT({$a}, ' ', {$b})";
    }

    public static function monthOf(string $column): string
    {
        return self::isPgsql() ? "extract(month from {$column})::int" : "MONTH({$column})";
    }

    public static function groupByMonthOf(string $column): string
    {
        return self::isPgsql() ? "extract(month from {$column})" : "MONTH({$column})";
    }

    public static function orderByDescNullsLast(string $column): string
    {
        // MySQL ya ordena los NULL al final en DESC; Postgres los pone primero salvo que se indique.
        return self::isPgsql() ? "{$column} DESC NULLS LAST" : "{$column} DESC";
    }

    public static function notBlankCheck(string $column): string
    {
        return self::isPgsql() ? "btrim({$column}) <> ''" : "TRIM({$column}) <> ''";
    }

    public static function regexCheck(string $column, string $pattern): string
    {
        // COLLATE ..._bin fuerza comparación sensible a mayúsculas: el collation utf8mb4
        // por defecto de MySQL hace que REGEXP ignore el caso, a diferencia de `~` en Postgres.
        // (BINARY en vez de COLLATE falla: mezcla el charset "binary" con utf8mb4 en regexp_like.)
        return self::isPgsql() ? "{$column} ~ '{$pattern}'" : "{$column} COLLATE utf8mb4_bin REGEXP '{$pattern}'";
    }

    public static function dropCheck(string $table, string $constraint): string
    {
        return self::isPgsql()
            ? "ALTER TABLE {$table} DROP CONSTRAINT {$constraint}"
            : "ALTER TABLE {$table} DROP CHECK {$constraint}";
    }
}

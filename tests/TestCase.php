<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guarda de seguridad ejecutada ANTES de que cualquier trait destructivo
     * (RefreshDatabase, DatabaseMigrations, DatabaseTruncation) borre o migre.
     *
     * Impide que las pruebas destruyan una base que no sea PostgreSQL de pruebas
     * (por ejemplo horus_prueba, la base de desarrollo). No basta con APP_ENV=testing.
     *
     * Se implementa en setUpTraits() y no en beforeRefreshingDatabase() porque
     * el método del trait RefreshDatabase tendría prioridad y anularía la guarda.
     *
     * @return array<class-string, class-string>
     */
    protected function setUpTraits()
    {
        $uses = class_uses_recursive(static::class);

        if (array_intersect([RefreshDatabase::class, DatabaseMigrations::class, DatabaseTruncation::class], $uses) !== []) {
            $this->assertSafeTestingDatabase();
        }

        return parent::setUpTraits();
    }

    protected function assertSafeTestingDatabase(): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($driver !== 'pgsql' || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Pruebas abortadas: se requiere PostgreSQL con una base terminada en "_test" (actual: %s / %s).',
                $driver,
                $database,
            ));
        }
    }
}

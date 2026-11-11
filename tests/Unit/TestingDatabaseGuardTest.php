<?php

use Illuminate\Support\Facades\DB;

/*
| Verifica la guarda de Tests\TestCase sin abrir conexión real: el nombre de
| la base se lee de la configuración, y el PDO de Laravel es perezoso.
*/

afterEach(function () {
    DB::purge('pgsql');
});

it('blocks destructive tests against the development database', function () {
    config(['database.connections.pgsql.database' => 'horus_prueba']);
    DB::purge('pgsql');

    expect(fn () => $this->assertSafeTestingDatabase())
        ->toThrow(RuntimeException::class, 'horus_prueba');
});

it('allows the isolated testing database', function () {
    config(['database.connections.pgsql.database' => 'horus_prueba_test']);
    DB::purge('pgsql');

    $this->assertSafeTestingDatabase();

    expect(true)->toBeTrue();
});

<?php

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature: pruebas contra PostgreSQL aislado (horus_prueba_test) con
| RefreshDatabase. Tests\TestCase aborta si la base no termina en "_test".
|
| Unit: sin base de datos, pero con la aplicación arrancada para leer config().
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
| Concurrency: varios procesos PHP reales compiten por el correlativo. Los datos
| deben estar CONFIRMADOS para que otros procesos los vean, por eso se usa
| DatabaseTruncation (también protegido por la guarda de Tests\TestCase).
*/

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Concurrency');

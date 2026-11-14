<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

trait InteractsWithToasts
{
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }

    /**
     * SQLSTATE al borrar un registro referenciado:
     * Postgres: 23001 = restrict_violation (ON DELETE RESTRICT), 23503 = foreign_key_violation (NO ACTION).
     * MySQL: 23000 = integrity_constraint_violation (genérico; en un delete casi siempre es la FK).
     */
    private const REFERENCED_ROW_CODES = ['23001', '23503', '23000'];

    /**
     * Elimina el modelo. Si la FK lo impide, muestra un aviso en lugar de un
     * error 500. Devuelve true si se eliminó.
     *
     * Se ejecuta en su propia transacción (SAVEPOINT si ya hay una abierta) para que
     * el fallo no deje abortada la transacción externa en PostgreSQL.
     */
    protected function deleteProtected(Model $model, string $blockedMessage): bool
    {
        try {
            DB::transaction(fn () => $model->delete());

            return true;
        } catch (QueryException $e) {
            if (! in_array((string) $e->getCode(), self::REFERENCED_ROW_CODES, true)) {
                throw $e;
            }

            $this->toast($blockedMessage, 'error');

            return false;
        }
    }
}

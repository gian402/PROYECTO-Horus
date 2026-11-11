<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Elimina archivos de importación subidos y nunca confirmados (vistas previas abandonadas).
 * Se programa a diario en routes/console.php.
 */
#[Signature('horus:prune-imports {--hours=24 : Antigüedad mínima en horas}')]
#[Description('Elimina archivos de importación abandonados en el disco privado')]
class PruneImports extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $limit = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $deleted = 0;

        foreach ($disk->files('imports') as $file) {
            if ($disk->lastModified($file) < $limit) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Archivos eliminados: {$deleted}.");

        return self::SUCCESS;
    }
}

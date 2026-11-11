<?php

namespace App\Http\Controllers;

use App\Exports\ImportTemplateExport;
use App\Http\Controllers\Concerns\InteractsWithToasts;
use App\Services\Import\HistoricalImporter;
use App\Services\Import\ImportRow;
use App\Services\Import\LegacyWorkbookReader;
use App\Services\Import\RowsImport;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Importación histórica en dos pasos: subir y validar (sin escribir) → confirmar.
 * El archivo subido se guarda en el disco privado y su ruta solo vive en la
 * sesión de quien lo subió.
 */
class ImportController extends Controller
{
    use InteractsWithToasts;

    private const SESSION_KEY = 'import.preview';

    private const DISK = 'local';

    public function __construct(
        private readonly HistoricalImporter $importer,
        private readonly LegacyWorkbookReader $legacyReader,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('imports.run');

        $preview = $request->session()->get(self::SESSION_KEY);

        return Inertia::render('imports/Index', [
            'columns' => HistoricalImporter::COLUMNS,
            'maxRows' => HistoricalImporter::MAX_ROWS,
            'preview' => $preview ? collect($preview)->except('path')->all() : null,
        ]);
    }

    public function template(): BinaryFileResponse
    {
        Gate::authorize('imports.run');

        return Excel::download(new ImportTemplateExport, 'plantilla-importacion-horus.xlsx');
    }

    public function preview(Request $request): RedirectResponse
    {
        Gate::authorize('imports.run');

        $request->validate(
            ['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']],
            ['file.mimes' => 'El archivo debe ser Excel (.xlsx, .xls) o CSV.'],
            ['file' => 'archivo'],
        );

        $this->discardPreview($request);

        $path = $request->file('file')->storeAs('imports', Str::uuid().'.'.$request->file('file')->getClientOriginalExtension(), self::DISK);

        try {
            $analysis = $this->analyze($path);
        } catch (Throwable) {
            Storage::disk(self::DISK)->delete($path);

            return back()->withErrors(['file' => 'No se pudo leer el archivo. Verifica que sea la plantilla del CRM o el libro de cursos.']);
        }

        if ($analysis['fatal']) {
            Storage::disk(self::DISK)->delete($path);

            return back()->withErrors(['file' => $analysis['fatal']]);
        }

        $rows = collect($analysis['rows']);

        $request->session()->put(self::SESSION_KEY, [
            'path' => $path,
            'filename' => $request->file('file')->getClientOriginalName(),
            'total' => $rows->count(),
            'valid' => $analysis['valid'],
            'invalid' => $analysis['invalid'],
            'format' => $analysis['format'],
            'courses_to_create' => $analysis['courses_to_create'],
            'speakers' => $rows->filter(fn (ImportRow $r) => $r->isSpeaker())->count(),
            // Para mostrar: todas las filas con error, los avisos y una muestra de las válidas.
            'errors' => $rows->reject(fn (ImportRow $r) => $r->isValid())->take(200)->map->toArray()->values()->all(),
            'warnings' => $rows->filter(fn (ImportRow $r) => $r->warnings !== [])->take(200)->map->toArray()->values()->all(),
            'sample' => $rows->filter(fn (ImportRow $r) => $r->isValid())->take(20)->map->toArray()->values()->all(),
        ]);

        return to_route('imports.index');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('imports.run');

        $preview = $request->session()->get(self::SESSION_KEY);

        if (! $preview || ! Storage::disk(self::DISK)->exists($preview['path'])) {
            $this->toast('No hay un archivo validado para importar. Súbelo de nuevo.', 'error');

            return to_route('imports.index');
        }

        // Se vuelve a analizar: la base pudo cambiar desde la vista previa.
        $analysis = $this->analyze($preview['path']);

        try {
            $summary = $this->importer->import($analysis['rows']);
        } catch (DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return to_route('imports.index');
        }

        $this->discardPreview($request);

        $this->toast(sprintf(
            'Importación completada: %d matrículas nuevas, %d ya existían; se crearon %d participantes, %d cursos y %d ediciones.',
            $summary['enrollments_created'],
            $summary['enrollments_existing'],
            $summary['students_created'],
            $summary['courses_created'],
            $summary['editions_created'],
        ));

        return to_route('imports.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('imports.run');

        $this->discardPreview($request);

        return to_route('imports.index');
    }

    /**
     * Detecta el formato: si la primera hoja tiene las columnas de la plantilla
     * se usa el flujo plano; si no, se lee como libro de cursos histórico.
     *
     * @return array{rows: list<ImportRow>, valid: int, invalid: int, fatal: ?string, courses_to_create: list<array{code: string, name: string}>, format: string}
     */
    private function analyze(string $path): array
    {
        $sheets = Excel::toArray(new RowsImport, $path, self::DISK);
        $first = $sheets[0] ?? [];
        $headings = array_keys($first[0] ?? []);

        if (in_array('dni', $headings, true) && in_array('codigo_curso', $headings, true)) {
            return [...$this->importer->analyze($first), 'format' => 'template'];
        }

        $rows = $this->legacyReader->read(Storage::disk(self::DISK)->path($path));

        return [...$this->importer->analyzeRows($rows), 'format' => 'legacy'];
    }

    private function discardPreview(Request $request): void
    {
        $preview = $request->session()->pull(self::SESSION_KEY);

        if ($preview && isset($preview['path'])) {
            Storage::disk(self::DISK)->delete($preview['path']);
        }
    }
}

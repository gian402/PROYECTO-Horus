<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Upload } from '@lucide/vue';
import { ref } from 'vue';
import ImportController from '@/actions/App/Http/Controllers/ImportController';
import PageHeader from '@/components/crm/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatPeriod } from '@/lib/format';
import { index } from '@/routes/imports';

type PreviewRow = {
    line: number;
    sheet: string | null;
    origin: string;
    role: 'participante' | 'ponente';
    warnings: string[];
    dni: string;
    name: string;
    course_code: string;
    start_date: string | null;
    end_date: string | null;
    year: number | null;
    registration_code: string | null;
    grade: string | null;
    errors: string[];
};

defineProps<{
    columns: string[];
    maxRows: number;
    preview: {
        filename: string;
        total: number;
        valid: number;
        invalid: number;
        format: 'template' | 'legacy';
        speakers: number;
        courses_to_create: { code: string; name: string }[];
        errors: PreviewRow[];
        warnings: PreviewRow[];
        sample: PreviewRow[];
    } | null;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Importar', href: index() }] },
});

const importing = ref(false);

function confirmImport(): void {
    router.post(
        ImportController.store.url(),
        {},
        {
            onStart: () => (importing.value = true),
            onFinish: () => (importing.value = false),
        },
    );
}

function discard(): void {
    router.delete(ImportController.destroy.url());
}
</script>

<template>
    <Head title="Importar historial" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Importar historial desde Excel"
            description="Sube el libro de cursos tal como lo usan (una hoja por curso, p. ej. «CURSOS 2024.xlsx») o la plantilla del CRM. Primero se valida el archivo completo sin guardar nada; luego confirmas la importación."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <a :href="ImportController.template.url()">
                        <Download /> Descargar plantilla
                    </a>
                </Button>
            </template>
        </PageHeader>

        <section class="space-y-2 rounded-xl border p-4 text-sm">
            <h2 class="font-semibold">Libro de cursos (formato histórico)</h2>
            <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
                <li>
                    Cada hoja con los encabezados «APELLIDOS Y NOMBRES», «DNI» y
                    «CÓD. REGISTRO» es un curso; las demás (como INICIO) se
                    ignoran. «NOTA» y «CELULAR» son opcionales.
                </li>
                <li>
                    El código y el año del curso se toman de los códigos de
                    registro (HGC-NUT-DIET-01-2024 → NUT-DIET, 2024). Los cursos
                    que no existan se crean con el nombre de la hoja.
                </li>
                <li>
                    Las filas debajo de «PONENTE» se importan como ponentes. Sin
                    nota, los participantes quedan aprobados (registro
                    histórico) y la edición queda sin fechas.
                </li>
                <li>
                    En «APELLIDOS Y NOMBRES», las dos primeras palabras se toman
                    como apellidos. Revisa la muestra antes de confirmar.
                </li>
            </ul>
        </section>

        <section
            class="grid gap-4 rounded-xl border p-4 text-sm md:grid-cols-2"
        >
            <div class="space-y-2">
                <h2 class="font-semibold">Plantilla del CRM: columnas</h2>
                <p class="flex flex-wrap gap-1">
                    <code
                        v-for="c in columns"
                        :key="c"
                        class="rounded bg-muted px-1.5 py-0.5 text-xs"
                        >{{ c }}</code
                    >
                </p>
            </div>
            <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
                <li>
                    El curso (<code>codigo_curso</code>) debe existir en el
                    catálogo.
                </li>
                <li>
                    Fechas en formato AAAA-MM-DD o DD/MM/AAAA. Si falta
                    <code>anio</code>, se toma de la fecha de inicio.
                </li>
                <li>
                    <code>codigo_registro</code> es opcional: si viene, se
                    respeta; si no, se genera el correlativo.
                </li>
                <li>
                    Importar el mismo archivo dos veces no duplica registros.
                    Máximo {{ maxRows }} filas.
                </li>
            </ul>
        </section>

        <Form
            v-if="!preview"
            v-bind="ImportController.preview.form()"
            class="flex flex-col gap-3 rounded-xl border p-4"
            v-slot="{ errors, processing }"
        >
            <Label for="file">Archivo (.xlsx, .xls o .csv, máx. 5 MB)</Label>
            <input
                id="file"
                name="file"
                type="file"
                accept=".xlsx,.xls,.csv"
                required
                class="text-sm file:mr-3 file:rounded-md file:border file:bg-background file:px-3 file:py-1.5 file:text-sm"
            />
            <InputError :message="errors.file" />
            <div>
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    <Upload v-else /> Validar archivo
                </Button>
            </div>
        </Form>

        <template v-else>
            <section class="flex flex-col gap-4 rounded-xl border p-4">
                <div class="flex flex-wrap items-center gap-2">
                    <FileSpreadsheet class="size-5 text-muted-foreground" />
                    <span class="font-medium">{{ preview.filename }}</span>
                </div>
                <p class="text-sm text-muted-foreground">
                    Formato detectado:
                    {{
                        preview.format === 'legacy'
                            ? 'libro de cursos (histórico)'
                            : 'plantilla del CRM'
                    }}<span v-if="preview.speakers">
                        · {{ preview.speakers }} ponentes</span
                    >
                </p>
                <dl class="grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-muted-foreground">Filas</dt>
                        <dd class="text-lg font-semibold tabular-nums">
                            {{ preview.total }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Válidas</dt>
                        <dd
                            class="text-lg font-semibold text-emerald-700 tabular-nums dark:text-emerald-400"
                        >
                            {{ preview.valid }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Con errores</dt>
                        <dd
                            class="text-lg font-semibold tabular-nums"
                            :class="
                                preview.invalid
                                    ? 'text-rose-700 dark:text-rose-400'
                                    : ''
                            "
                        >
                            {{ preview.invalid }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="preview.courses_to_create.length"
                    class="rounded-md border border-sky-300 bg-sky-50 px-3 py-2 text-sm text-sky-900 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-100"
                >
                    Se crearán estos cursos:
                    <ul class="mt-1 list-disc pl-5">
                        <li
                            v-for="c in preview.courses_to_create"
                            :key="c.code"
                        >
                            <span class="font-mono">{{ c.code }}</span> ·
                            {{ c.name }}
                        </li>
                    </ul>
                </div>

                <p
                    v-if="preview.invalid"
                    class="rounded-md border border-rose-300 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-100"
                >
                    No se importará nada mientras haya errores. Corrige el
                    archivo y vuelve a subirlo.
                </p>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="!preview.invalid"
                        :disabled="importing"
                        @click="confirmImport"
                    >
                        <Spinner v-if="importing" />
                        Confirmar importación ({{ preview.valid }} filas)
                    </Button>
                    <Button variant="outline" @click="discard">
                        {{
                            preview.invalid ? 'Subir otro archivo' : 'Cancelar'
                        }}
                    </Button>
                </div>
            </section>

            <section v-if="preview.errors.length" class="flex flex-col gap-2">
                <h2 class="text-base font-semibold">Errores por fila</h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-2 font-medium">
                                    Fila
                                </th>
                                <th scope="col" class="px-4 py-2 font-medium">
                                    DNI / participante
                                </th>
                                <th scope="col" class="px-4 py-2 font-medium">
                                    Errores
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in preview.errors"
                                :key="row.origin"
                                class="border-t align-top"
                            >
                                <td class="px-4 py-2">
                                    {{ row.origin }}
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-mono">{{
                                        row.dni || '—'
                                    }}</span>
                                    <div class="text-muted-foreground">
                                        {{ row.name || '—' }}
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <ul
                                        class="list-disc pl-4 text-rose-700 dark:text-rose-400"
                                    >
                                        <li
                                            v-for="(e, i) in row.errors"
                                            :key="i"
                                        >
                                            {{ e }}
                                        </li>
                                    </ul>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section v-if="preview.warnings.length" class="flex flex-col gap-2">
                <h2 class="text-base font-semibold">
                    Avisos (no impiden importar)
                </h2>
                <ul class="space-y-1 rounded-xl border p-4 text-sm">
                    <li v-for="row in preview.warnings" :key="row.origin">
                        <span class="text-muted-foreground"
                            >{{ row.origin }}:</span
                        >
                        {{ row.name }} — {{ row.warnings.join(' ') }}
                    </li>
                </ul>
            </section>

            <section v-if="preview.sample.length" class="flex flex-col gap-2">
                <h2 class="text-base font-semibold">
                    Muestra de filas válidas
                </h2>
                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th scope="col" class="px-4 py-2 font-medium">
                                    Fila
                                </th>
                                <th scope="col" class="px-4 py-2 font-medium">
                                    Apellidos, nombres
                                </th>
                                <th scope="col" class="px-4 py-2 font-medium">
                                    Curso / periodo
                                </th>
                                <th scope="col" class="px-4 py-2 font-medium">
                                    Código
                                </th>
                                <th
                                    scope="col"
                                    class="px-4 py-2 text-right font-medium"
                                >
                                    Nota
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in preview.sample"
                                :key="row.origin"
                                class="border-t"
                            >
                                <td class="px-4 py-2">{{ row.origin }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-mono">{{ row.dni }}</span>
                                    · {{ row.name }}
                                    <span
                                        v-if="row.role === 'ponente'"
                                        class="ml-1 rounded-md bg-violet-100 px-1.5 py-0.5 text-xs text-violet-800 dark:bg-violet-950 dark:text-violet-200"
                                        >Ponente</span
                                    >
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    {{ row.course_code }} ·
                                    {{
                                        formatPeriod(
                                            row.start_date,
                                            row.end_date,
                                            row.year ?? 0,
                                        )
                                    }}
                                </td>
                                <td class="px-4 py-2 font-mono">
                                    {{ row.registration_code ?? 'Se generará' }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ row.grade ?? '—' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </template>
    </div>
</template>

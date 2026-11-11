<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Award,
    Download,
    Pencil,
    RefreshCw,
    Trash2,
    UserPlus,
    XCircle,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import CertificateController from '@/actions/App/Http/Controllers/CertificateController';
import CourseEditionController from '@/actions/App/Http/Controllers/CourseEditionController';
import EnrollmentController from '@/actions/App/Http/Controllers/EnrollmentController';
import GradeController from '@/actions/App/Http/Controllers/GradeController';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatPeriod } from '@/lib/format';
import { index } from '@/routes/editions';
import type {
    EditionDetail,
    EditionEnrollment,
    StudentSearchResult,
} from '@/types';

const props = defineProps<{
    edition: EditionDetail;
    enrollments: EditionEnrollment[];
    passingGrade: number;
    can: {
        update: boolean;
        delete: boolean;
        enroll: boolean;
        grade: boolean;
        issueCertificates: boolean;
        downloadCertificates: boolean;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ediciones', href: index() }] },
});

/* ---------- Matricular ---------- */
const query = ref('');
const results = ref<StudentSearchResult[]>([]);
const searching = ref(false);
const enrollError = ref<string | undefined>();
const asSpeaker = ref(false);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    clearTimeout(searchTimer);
    enrollError.value = undefined;

    if (value.trim().length < 2) {
        results.value = [];

        return;
    }

    searchTimer = setTimeout(async () => {
        searching.value = true;

        try {
            const url = `${EnrollmentController.searchStudents.url(props.edition.id)}?q=${encodeURIComponent(value.trim())}`;
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            results.value = response.ok ? await response.json() : [];
        } finally {
            searching.value = false;
        }
    }, 300);
});

function enroll(student: StudentSearchResult): void {
    router.post(
        EnrollmentController.store.url(props.edition.id),
        { student_id: student.id, as_speaker: asSpeaker.value },
        {
            preserveScroll: true,
            onError: (e) => (enrollError.value = e.student_id),
            onSuccess: () => {
                query.value = '';
                results.value = [];
                asSpeaker.value = false;
            },
        },
    );
}

/* ---------- Notas ---------- */
const grades = reactive<Record<number, string>>({});
const gradeErrors = ref<Record<string, string>>({});
const savingGrades = ref(false);

function resetGrades(): void {
    for (const e of props.enrollments) {
        grades[e.id] = e.grade ?? '';
    }
}

resetGrades();
watch(() => props.enrollments, resetGrades);

const dirtyGrades = computed(() =>
    props.enrollments.filter((e) => (grades[e.id] ?? '') !== (e.grade ?? '')),
);

function previewStatus(e: EditionEnrollment): EditionEnrollment['status'] {
    const raw = (grades[e.id] ?? '').replace(',', '.').trim();

    // Ponentes e históricos sin nota quedan aprobados (misma regla que el backend).
    if (e.role === 'ponente' || (raw === '' && e.historical)) {
        return 'aprobado';
    }

    if (raw === '' || Number.isNaN(Number(raw))) {
        return raw === '' ? 'cursando' : e.status;
    }

    return Number(raw) >= props.passingGrade ? 'aprobado' : 'desaprobado';
}

function saveGrades(): void {
    const payload: Record<number, string | null> = {};

    for (const e of dirtyGrades.value) {
        payload[e.id] = grades[e.id] === '' ? null : grades[e.id];
    }

    router.put(
        GradeController.update.url(props.edition.id),
        { grades: payload },
        {
            preserveScroll: true,
            onStart: () => (savingGrades.value = true),
            onFinish: () => (savingGrades.value = false),
            onError: (e) => (gradeErrors.value = e),
            onSuccess: () => (gradeErrors.value = {}),
        },
    );
}

function gradeError(id: number): string | undefined {
    return gradeErrors.value[`grades.${id}`];
}

/* ---------- Certificados ---------- */
function issue(e: EditionEnrollment): void {
    router.post(
        CertificateController.store.url(e.id),
        {},
        { preserveScroll: true },
    );
}

type ReasonAction = {
    kind: 'reissue' | 'revoke';
    enrollment: EditionEnrollment;
};
const reasonAction = ref<ReasonAction | null>(null);
const reason = ref('');
const reasonError = ref<string | undefined>();
const reasonProcessing = ref(false);

function openReason(
    kind: ReasonAction['kind'],
    enrollment: EditionEnrollment,
): void {
    reasonAction.value = { kind, enrollment };
    reason.value = '';
    reasonError.value = undefined;
}

function submitReason(): void {
    const action = reasonAction.value;

    if (!action || !action.enrollment.certificate) {
        return;
    }

    const url =
        action.kind === 'reissue'
            ? CertificateController.reissue.url(action.enrollment.id)
            : CertificateController.revoke.url(
                  action.enrollment.certificate.id,
              );

    router.post(
        url,
        { reason: reason.value },
        {
            preserveScroll: true,
            onStart: () => (reasonProcessing.value = true),
            onFinish: () => (reasonProcessing.value = false),
            onError: (e) => (reasonError.value = e.reason),
            onSuccess: () => (reasonAction.value = null),
        },
    );
}

/* ---------- Anular matrícula / eliminar edición ---------- */
const toRemove = ref<EditionEnrollment | null>(null);

function removeEnrollment(): void {
    if (!toRemove.value) {
        return;
    }

    router.delete(EnrollmentController.destroy.url(toRemove.value.id), {
        preserveScroll: true,
        onFinish: () => (toRemove.value = null),
    });
}

const confirmDeleteEdition = ref(false);

function destroyEdition(): void {
    router.delete(CourseEditionController.destroy.url(props.edition.id), {
        onFinish: () => (confirmDeleteEdition.value = false),
    });
}

// Solo participantes: los ponentes no se califican (igual que panel y reportes).
const approvedCount = computed(
    () =>
        props.enrollments.filter(
            (e) => e.role === 'participante' && e.status === 'aprobado',
        ).length,
);
</script>

<template>
    <Head :title="`${edition.course.code} · ${edition.course.name}`" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="`${edition.course.code} · ${edition.course.name}`"
            :description="`${formatPeriod(edition.start_date, edition.end_date, edition.year)} · Año ${edition.year} · Ponente: ${edition.teacher?.full_name ?? 'sin asignar'}`"
        >
            <template #actions>
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="CourseEditionController.edit.url(edition.id)">
                        <Pencil /> Editar
                    </Link>
                </Button>
                <ConfirmDialog
                    v-if="can.delete"
                    v-model:open="confirmDeleteEdition"
                    title="¿Eliminar edición?"
                    description="Solo es posible si no tiene matrículas."
                    confirm-label="Eliminar"
                    destructive
                    @confirm="destroyEdition"
                >
                    <template #trigger>
                        <Button variant="ghost" class="text-destructive">
                            <Trash2 /> Eliminar
                        </Button>
                    </template>
                </ConfirmDialog>
            </template>
        </PageHeader>

        <dl class="grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-muted-foreground">Matriculados</dt>
                <dd class="text-lg font-semibold tabular-nums">
                    {{ enrollments.length }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Aprobados</dt>
                <dd class="text-lg font-semibold tabular-nums">
                    {{ approvedCount }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Nota mínima aprobatoria</dt>
                <dd class="text-lg font-semibold tabular-nums">
                    {{ passingGrade }}
                </dd>
            </div>
        </dl>

        <!-- Matricular -->
        <section
            v-if="can.enroll"
            class="flex flex-col gap-2 rounded-xl border p-4"
        >
            <Label for="enroll-search" class="flex items-center gap-2">
                <UserPlus class="size-4" /> Matricular participante
            </Label>
            <Input
                id="enroll-search"
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="Busca por DNI o apellidos (mín. 2 caracteres)"
                class="max-w-md"
            />
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="asSpeaker"
                    type="checkbox"
                    class="size-4 rounded border-input accent-primary"
                />
                Matricular como ponente (recibe código y certificado, sin nota)
            </label>
            <InputError :message="enrollError" />
            <p v-if="searching" class="text-sm text-muted-foreground">
                Buscando…
            </p>
            <p
                v-else-if="query.trim().length >= 2 && results.length === 0"
                class="text-sm text-muted-foreground"
            >
                Sin resultados (o ya están matriculados). Registra primero al
                participante en Participantes.
            </p>
            <ul
                v-if="results.length"
                class="max-w-md divide-y rounded-md border"
            >
                <li
                    v-for="s in results"
                    :key="s.id"
                    class="flex items-center justify-between gap-2 px-3 py-2 text-sm"
                >
                    <span
                        ><span class="font-mono">{{ s.dni }}</span> ·
                        {{ s.full_name }}</span
                    >
                    <Button size="sm" @click="enroll(s)">Matricular</Button>
                </li>
            </ul>
        </section>

        <!-- Matriculados, notas y certificados -->
        <section class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">Matriculados</h2>
                <Button
                    v-if="can.grade && enrollments.length"
                    :disabled="dirtyGrades.length === 0 || savingGrades"
                    @click="saveGrades"
                >
                    Guardar notas
                    <span v-if="dirtyGrades.length" class="tabular-nums"
                        >({{ dirtyGrades.length }})</span
                    >
                </Button>
            </div>
            <InputError :message="gradeErrors.grades" />

            <EmptyState
                v-if="enrollments.length === 0"
                title="Aún no hay matriculados"
            />

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Código
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Participante
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Nota (0–20)
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Estado
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Certificado
                            </th>
                            <th scope="col" class="px-4 py-3">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="e in enrollments"
                            :key="e.id"
                            class="border-t align-top"
                        >
                            <td class="px-4 py-3 font-mono whitespace-nowrap">
                                {{ e.registration_code }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    class="flex flex-wrap items-center gap-2 font-medium"
                                >
                                    {{ e.student.full_name }}
                                    <span
                                        v-if="e.role === 'ponente'"
                                        class="rounded-md bg-violet-100 px-1.5 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-950 dark:text-violet-200"
                                        >Ponente</span
                                    >
                                    <span
                                        v-if="e.historical"
                                        class="rounded-md bg-muted px-1.5 py-0.5 text-xs font-normal text-muted-foreground"
                                        title="Importado del registro histórico en Excel"
                                        >Histórico</span
                                    >
                                </div>
                                <div class="font-mono text-muted-foreground">
                                    {{ e.student.dni }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="e.role === 'ponente'"
                                    class="text-muted-foreground"
                                    >No aplica</span
                                >
                                <Input
                                    v-else-if="can.grade"
                                    v-model="grades[e.id]"
                                    inputmode="decimal"
                                    class="w-24 tabular-nums"
                                    :aria-label="`Nota de ${e.student.full_name}`"
                                    :aria-invalid="!!gradeError(e.id)"
                                />
                                <span v-else class="tabular-nums">{{
                                    e.grade ?? '—'
                                }}</span>
                                <InputError
                                    class="mt-1"
                                    :message="gradeError(e.id)"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="
                                        can.grade ? previewStatus(e) : e.status
                                    "
                                />
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-if="e.certificate"
                                    class="flex flex-wrap items-center gap-1"
                                >
                                    <span class="mr-1 text-muted-foreground"
                                        >Emitido
                                        {{
                                            formatDate(e.certificate.issued_at)
                                        }}</span
                                    >
                                    <Button
                                        v-if="can.downloadCertificates"
                                        variant="outline"
                                        size="icon-sm"
                                        as-child
                                    >
                                        <a
                                            :href="
                                                CertificateController.download.url(
                                                    e.certificate.id,
                                                )
                                            "
                                            :aria-label="`Descargar certificado de ${e.student.full_name}`"
                                            ><Download
                                        /></a>
                                    </Button>
                                    <template v-if="can.issueCertificates">
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            :aria-label="`Reemitir certificado de ${e.student.full_name}`"
                                            @click="openReason('reissue', e)"
                                            ><RefreshCw
                                        /></Button>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-destructive"
                                            :aria-label="`Anular certificado de ${e.student.full_name}`"
                                            @click="openReason('revoke', e)"
                                            ><XCircle
                                        /></Button>
                                    </template>
                                </div>
                                <Button
                                    v-else-if="
                                        can.issueCertificates &&
                                        e.status === 'aprobado'
                                    "
                                    size="sm"
                                    variant="outline"
                                    @click="issue(e)"
                                    ><Award /> Emitir</Button
                                >
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="can.enroll && !e.certificate"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive"
                                    :aria-label="`Anular matrícula de ${e.student.full_name}`"
                                    @click="toRemove = e"
                                    ><Trash2
                                /></Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="can.grade" class="text-xs text-muted-foreground">
                El estado se calcula automáticamente: sin nota = cursando; nota
                ≥ {{ passingGrade }} = aprobado; menor = desaprobado. Los
                ponentes y los registros históricos sin nota figuran como
                aprobados.
            </p>
        </section>

        <ConfirmDialog
            :open="toRemove !== null"
            :title="`¿Anular la matrícula ${toRemove?.registration_code ?? ''}?`"
            description="Se elimina la matrícula. Solo es posible si no tiene certificados emitidos."
            confirm-label="Anular matrícula"
            destructive
            @update:open="(v) => !v && (toRemove = null)"
            @confirm="removeEnrollment"
        />

        <ConfirmDialog
            :open="reasonAction !== null"
            :title="
                reasonAction?.kind === 'reissue'
                    ? 'Reemitir certificado'
                    : 'Anular certificado'
            "
            :description="
                reasonAction?.kind === 'reissue'
                    ? 'El certificado vigente quedará anulado y se emitirá uno nuevo con otro código de verificación. El historial se conserva.'
                    : 'El certificado aparecerá como ANULADO en la verificación pública.'
            "
            :confirm-label="
                reasonAction?.kind === 'reissue' ? 'Reemitir' : 'Anular'
            "
            :destructive="reasonAction?.kind === 'revoke'"
            :processing="reasonProcessing"
            @update:open="(v) => !v && (reasonAction = null)"
            @confirm="submitReason"
        >
            <div class="grid gap-2">
                <Label for="reason">Motivo</Label>
                <Input
                    id="reason"
                    v-model="reason"
                    maxlength="255"
                    placeholder="Ej. Corrección del nombre del participante"
                    @input="reasonError = undefined"
                    @keydown.enter.prevent="!reasonProcessing && submitReason()"
                />
                <InputError :message="reasonError" />
            </div>
        </ConfirmDialog>
    </div>
</template>

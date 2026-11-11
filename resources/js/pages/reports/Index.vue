<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import CourseEditionController from '@/actions/App/Http/Controllers/CourseEditionController';
import ReportController from '@/actions/App/Http/Controllers/ReportController';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import Pagination from '@/components/crm/Pagination.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/reports';
import type { EnrollmentRole, EnrollmentStatus, Paginated } from '@/types';

type ReportRow = {
    id: number;
    registration_code: string;
    student: string;
    dni: string;
    course: string;
    edition_id: number;
    edition: string;
    year: number;
    grade: string | null;
    status: EnrollmentStatus;
    role: EnrollmentRole;
    certificate_issued_at: string | null;
};

type ReportFilters = {
    year: number | null;
    course_id: number | null;
    edition_id: number | null;
    status: EnrollmentStatus | null;
    certificate: 'with' | 'without' | null;
};

const props = defineProps<{
    rows: Paginated<ReportRow>;
    filters: ReportFilters;
    totals: {
        total: number;
        cursando: number;
        aprobado: number;
        desaprobado: number;
        certificates: number;
    };
    options: {
        courses: { id: number; code: string; name: string }[];
        years: number[];
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reportes', href: index() }] },
});

const form = reactive<ReportFilters>({ ...props.filters });

const query = computed(() => {
    const q: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(form)) {
        if (value !== null) {
            q[key] = value;
        }
    }

    return q;
});

watch(form, () => {
    router.get(ReportController.index.url(), query.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

const exportUrl = computed(() =>
    ReportController.export.url({ query: query.value }),
);

const approvalRate = computed(() => {
    const graded = props.totals.aprobado + props.totals.desaprobado;

    return graded ? Math.round((props.totals.aprobado / graded) * 100) : null;
});

const selectClass = 'h-9 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head title="Reportes" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Reporte de matrículas"
            description="Matrículas, notas y certificados con filtros. La exportación respeta los mismos filtros."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <a :href="exportUrl"><Download /> Exportar a Excel</a>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2">
            <select v-model="form.year" :class="selectClass" aria-label="Año">
                <option :value="null">Todos los años</option>
                <option v-for="y in options.years" :key="y" :value="y">
                    {{ y }}
                </option>
            </select>
            <select
                v-model="form.course_id"
                :class="selectClass"
                aria-label="Curso"
            >
                <option :value="null">Todos los cursos</option>
                <option v-for="c in options.courses" :key="c.id" :value="c.id">
                    {{ c.code }} – {{ c.name }}
                </option>
            </select>
            <select
                v-model="form.status"
                :class="selectClass"
                aria-label="Estado"
            >
                <option :value="null">Todos los estados</option>
                <option value="cursando">Cursando</option>
                <option value="aprobado">Aprobado</option>
                <option value="desaprobado">Desaprobado</option>
            </select>
            <select
                v-model="form.certificate"
                :class="selectClass"
                aria-label="Certificado"
            >
                <option :value="null">Con y sin certificado</option>
                <option value="with">Con certificado vigente</option>
                <option value="without">Sin certificado vigente</option>
            </select>
        </div>

        <dl class="grid gap-3 text-sm sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border p-3">
                <dt class="text-muted-foreground">Matrículas</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {{ totals.total }}
                </dd>
            </div>
            <div class="rounded-xl border p-3">
                <dt class="text-muted-foreground">Cursando</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {{ totals.cursando }}
                </dd>
            </div>
            <div class="rounded-xl border p-3">
                <dt class="text-muted-foreground">Aprobados</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {{ totals.aprobado }}
                </dd>
            </div>
            <div class="rounded-xl border p-3">
                <dt class="text-muted-foreground">Desaprobados</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {{ totals.desaprobado }}
                </dd>
            </div>
            <div class="rounded-xl border p-3">
                <dt class="text-muted-foreground">% aprobación</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {{ approvalRate === null ? '—' : `${approvalRate}%` }}
                </dd>
            </div>
            <div class="rounded-xl border p-3">
                <dt class="text-muted-foreground">Certificados vigentes</dt>
                <dd class="text-xl font-semibold tabular-nums">
                    {{ totals.certificates }}
                </dd>
            </div>
        </dl>

        <EmptyState
            v-if="rows.data.length === 0"
            title="No hay matrículas con estos filtros"
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
                            Edición
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-right font-medium"
                        >
                            Nota
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Estado
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Certificado
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows.data" :key="r.id" class="border-t">
                        <td class="px-4 py-3 font-mono whitespace-nowrap">
                            {{ r.registration_code }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ r.student }}</div>
                            <div class="font-mono text-muted-foreground">
                                {{ r.dni }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    CourseEditionController.show.url(
                                        r.edition_id,
                                    )
                                "
                                class="hover:underline"
                                >{{ r.edition }}</Link
                            >
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ r.grade ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="r.status" :role="r.role" />
                        </td>
                        <td class="px-4 py-3">
                            {{ formatDate(r.certificate_issued_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="rows" />
    </div>
</template>

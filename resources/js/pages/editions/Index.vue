<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { reactive, watch } from 'vue';
import CourseEditionController from '@/actions/App/Http/Controllers/CourseEditionController';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import Pagination from '@/components/crm/Pagination.vue';
import { Button } from '@/components/ui/button';
import { formatPeriod } from '@/lib/format';
import { index } from '@/routes/editions';
import type {
    CourseFinishedSummary,
    EditionOptions,
    EditionRow,
    Paginated,
} from '@/types';

const props = defineProps<{
    editions: Paginated<EditionRow>;
    filters: {
        course_id: number | null;
        teacher_id: number | null;
        year: number | null;
        status: 'en_curso' | 'finalizada' | null;
    };
    options: EditionOptions;
    years: number[];
    finishedByCourse: CourseFinishedSummary[];
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ediciones', href: index() }] },
});

const form = reactive({ ...props.filters });

watch(form, (value) => {
    router.get(
        CourseEditionController.index.url(),
        {
            course_id: value.course_id || undefined,
            teacher_id: value.teacher_id || undefined,
            year: value.year || undefined,
            status: value.status || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
});

const statusTabs: { value: typeof form.status; label: string }[] = [
    { value: null, label: 'Todas' },
    { value: 'en_curso', label: 'En curso' },
    { value: 'finalizada', label: 'Finalizadas' },
];

const selectClass = 'h-9 rounded-md border bg-background px-2 text-sm';
</script>

<template>
    <Head title="Ediciones" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Ediciones"
            description="Convocatorias de cada curso, con su ponente, fechas y matriculados."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="CourseEditionController.create.url()">
                        <Plus /> Nueva edición
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="finishedByCourse.length"
            class="flex flex-col gap-2 rounded-xl border p-4"
        >
            <h2 class="text-sm font-semibold">Ediciones finalizadas por curso</h2>
            <ul class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <li
                    v-for="c in finishedByCourse"
                    :key="c.id"
                    class="text-muted-foreground"
                >
                    <span class="font-mono">{{ c.code }}</span>
                    {{ c.name }}
                    <span class="font-semibold text-foreground">{{
                        c.finished_count
                    }}</span>
                </li>
            </ul>
        </div>

        <div class="flex gap-1 rounded-lg border bg-muted/40 p-1 w-fit">
            <button
                v-for="tab in statusTabs"
                :key="tab.label"
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                :class="
                    form.status === tab.value
                        ? 'bg-background shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="form.status = tab.value"
            >
                {{ tab.label }}
            </button>
        </div>

        <div class="flex flex-wrap gap-2">
            <select
                v-model="form.course_id"
                :class="selectClass"
                aria-label="Filtrar por curso"
            >
                <option :value="null">Todos los cursos</option>
                <option v-for="c in options.courses" :key="c.id" :value="c.id">
                    {{ c.code }} – {{ c.name }}
                </option>
            </select>
            <select
                v-model="form.teacher_id"
                :class="selectClass"
                aria-label="Filtrar por ponente"
            >
                <option :value="null">Todos los ponentes</option>
                <option v-for="t in options.teachers" :key="t.id" :value="t.id">
                    {{ t.full_name }}
                </option>
            </select>
            <select
                v-model="form.year"
                :class="selectClass"
                aria-label="Filtrar por año"
            >
                <option :value="null">Todos los años</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
        </div>

        <EmptyState
            v-if="editions.data.length === 0"
            title="No hay ediciones"
            description="Las ediciones agrupan a los participantes de un curso en un periodo y con un ponente."
        />

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Curso</th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Fechas
                        </th>
                        <th
                            scope="col"
                            class="hidden px-4 py-3 font-medium md:table-cell"
                        >
                            Ponente
                        </th>
                        <th
                            scope="col"
                            class="px-4 py-3 text-right font-medium"
                        >
                            Matriculados
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="edition in editions.data"
                        :key="edition.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    CourseEditionController.show.url(edition.id)
                                "
                                class="font-medium hover:underline"
                            >
                                <span class="font-mono">{{
                                    edition.course.code
                                }}</span>
                                · {{ edition.course.name }}
                            </Link>
                            <div class="flex items-center gap-2 text-muted-foreground">
                                <span>Año {{ edition.year }}</span>
                                <span
                                    v-if="edition.finished"
                                    class="rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground"
                                    >Finalizada</span
                                >
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{
                                formatPeriod(
                                    edition.start_date,
                                    edition.end_date,
                                    edition.year,
                                )
                            }}
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{ edition.teacher?.full_name ?? 'Sin asignar' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ edition.enrollments_count }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="editions" />
    </div>
</template>

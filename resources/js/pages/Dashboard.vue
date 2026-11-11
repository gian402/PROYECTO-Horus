<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CourseEditionController from '@/actions/App/Http/Controllers/CourseEditionController';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import { dashboard } from '@/routes';

const props = defineProps<{
    year: number;
    stats: {
        students: number | null;
        editions_this_year: number;
        enrollments: number;
        cursando: number;
        aprobado: number;
        desaprobado: number;
        active_certificates: number;
    };
    recentEditions: {
        id: number;
        label: string;
        course: string;
        enrollments_count: number;
    }[];
    canSeeAll: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Panel', href: dashboard() }] },
});

const cards = computed(() =>
    [
        props.stats.students !== null
            ? { label: 'Participantes registrados', value: props.stats.students }
            : null,
        {
            label: `Ediciones ${props.year}`,
            value: props.stats.editions_this_year,
        },
        { label: 'Matrículas', value: props.stats.enrollments },
        {
            label: 'Certificados vigentes',
            value: props.stats.active_certificates,
        },
    ].filter((c): c is { label: string; value: number } => c !== null),
);

const statusBars = computed(() => {
    const total = props.stats.enrollments || 1;

    return [
        { label: 'Cursando', value: props.stats.cursando, class: 'bg-sky-500' },
        { label: 'Aprobados', value: props.stats.aprobado, class: 'bg-emerald-500' },
        { label: 'Desaprobados', value: props.stats.desaprobado, class: 'bg-rose-500' },
    ].map((s) => ({ ...s, pct: Math.round((s.value / total) * 100) }));
});
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Panel"
            :description="
                canSeeAll
                    ? 'Resumen académico de Horus Group.'
                    : 'Resumen de las ediciones que dictas.'
            "
        />

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-xl border p-4"
            >
                <dt class="text-sm text-muted-foreground">{{ card.label }}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ card.value }}
                </dd>
            </div>
        </dl>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-xl border p-4">
                <h2 class="mb-4 text-base font-semibold">
                    Matrículas por estado
                </h2>
                <ul class="space-y-3">
                    <li v-for="s in statusBars" :key="s.label">
                        <div class="mb-1 flex justify-between text-sm">
                            <span>{{ s.label }}</span>
                            <span class="tabular-nums text-muted-foreground"
                                >{{ s.value }} · {{ s.pct }}%</span
                            >
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-label="s.label"
                            :aria-valuenow="s.pct"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full rounded-full"
                                :class="s.class"
                                :style="{ width: `${s.pct}%` }"
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border p-4">
                <h2 class="mb-4 text-base font-semibold">Ediciones recientes</h2>
                <EmptyState
                    v-if="recentEditions.length === 0"
                    title="Aún no hay ediciones"
                />
                <ul v-else class="divide-y">
                    <li
                        v-for="e in recentEditions"
                        :key="e.id"
                        class="flex items-center justify-between gap-2 py-2 text-sm"
                    >
                        <Link
                            :href="CourseEditionController.show.url(e.id)"
                            class="hover:underline"
                        >
                            <div class="font-medium">{{ e.course }}</div>
                            <div class="text-muted-foreground">
                                {{ e.label }}
                            </div>
                        </Link>
                        <span class="tabular-nums text-muted-foreground"
                            >{{ e.enrollments_count }} matr.</span
                        >
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>

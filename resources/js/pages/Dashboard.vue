<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    BarChart3,
    CalendarPlus,
    CalendarRange,
    ClipboardList,
    Download,
    FileUp,
    TrendingDown,
    TrendingUp,
    UserPlus,
    Users,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import CourseEditionController from '@/actions/App/Http/Controllers/CourseEditionController';
import StudentController from '@/actions/App/Http/Controllers/StudentController';
import DonutChart from '@/components/crm/DonutChart.vue';
import EmptyState from '@/components/crm/EmptyState.vue';
import MonthlyChart from '@/components/crm/MonthlyChart.vue';
import type { MonthlyPoint } from '@/components/crm/MonthlyChart.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { formatPeriod } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as editionsIndex } from '@/routes/editions';
import { index as importsIndex } from '@/routes/imports';
import { exportMethod, index as reportsIndex } from '@/routes/reports';

type Trend = { current: number; previous: number } | null;

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
    trends: {
        students: Trend;
        editions: Trend;
        enrollments: Trend;
        certificates: Trend;
    };
    monthly: (MonthlyPoint & { month: number })[];
    recentEnrollments: {
        id: number;
        student: string;
        course: string;
        role: 'participante' | 'ponente';
        registration_code: string;
        created_at: string | null;
    }[];
    recentEditions: {
        id: number;
        label: string;
        course: string;
        code: string;
        teacher: string | null;
        start_date: string | null;
        end_date: string | null;
        year: number;
        participants_count: number;
        approval_rate: number | null;
    }[];
    canSeeAll: boolean;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Panel', href: dashboard() }] },
});

const page = usePage();
const { can, canAny } = usePermissions();

const firstName = computed(
    () => page.props.auth.user.name.trim().split(/\s+/)[0] ?? '',
);

const today = (() => {
    const text = new Intl.DateTimeFormat('es-PE', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date());

    return text.charAt(0).toUpperCase() + text.slice(1);
})();

/* ---------- Tarjetas con tendencia (este mes frente al anterior) ---------- */
type TrendView = { text: string; tone: 'up' | 'down' | 'flat' };

function describeTrend(trend: Trend): TrendView | null {
    if (!trend) {
        return null;
    }

    if (trend.previous === 0) {
        return trend.current === 0
            ? { text: 'Sin novedades este mes', tone: 'flat' }
            : {
                  text: `${trend.current} ${trend.current === 1 ? 'nuevo' : 'nuevos'} este mes`,
                  tone: 'up',
              };
    }

    const pct = Math.round(
        ((trend.current - trend.previous) / trend.previous) * 100,
    );

    return {
        text:
            pct === 0
                ? 'Igual que el mes anterior'
                : `${Math.abs(pct)} % frente al mes anterior`,
        tone: pct > 0 ? 'up' : pct < 0 ? 'down' : 'flat',
    };
}

type StatCard = {
    label: string;
    value: number;
    icon: LucideIcon;
    tone: string;
    trend: TrendView | null;
};

const cards = computed<StatCard[]>(() =>
    [
        props.stats.students !== null
            ? {
                  label: 'Participantes',
                  value: props.stats.students,
                  icon: Users,
                  tone: 'bg-primary/12 text-primary',
                  trend: describeTrend(props.trends.students),
              }
            : null,
        {
            label: `Ediciones ${props.year}`,
            value: props.stats.editions_this_year,
            icon: CalendarRange,
            tone: 'bg-amber-soft/40 text-amber-700 dark:text-amber-soft',
            trend: describeTrend(props.trends.editions),
        },
        {
            label: 'Matrículas',
            value: props.stats.enrollments,
            icon: ClipboardList,
            tone: 'bg-sky-500/15 text-sky-600 dark:text-sky-300',
            trend: describeTrend(props.trends.enrollments),
        },
        {
            label: 'Certificados vigentes',
            value: props.stats.active_certificates,
            icon: Award,
            tone: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-300',
            trend: describeTrend(props.trends.certificates),
        },
    ].filter((c): c is StatCard => c !== null),
);

const trendClass = {
    up: 'text-emerald-600 dark:text-emerald-400',
    down: 'text-rose-600 dark:text-rose-400',
    flat: 'text-muted-foreground',
};

/* ---------- Gráficos ---------- */
const donutSegments = computed(() => [
    { label: 'Cursando', value: props.stats.cursando, color: '#0ea5e9' },
    { label: 'Aprobados', value: props.stats.aprobado, color: '#10b981' },
    { label: 'Desaprobados', value: props.stats.desaprobado, color: '#f43f5e' },
]);

const hasMonthlyData = computed(() =>
    props.monthly.some((m) => m.enrollments > 0 || m.certificates > 0),
);

/* ---------- Accesos rápidos según permisos ---------- */
const quickLinks = computed(() =>
    [
        can('editions.manage')
            ? {
                  label: 'Nueva edición',
                  href: CourseEditionController.create.url(),
                  icon: CalendarPlus,
                  primary: true,
              }
            : null,
        can('students.manage')
            ? {
                  label: 'Registrar participante',
                  href: StudentController.create.url(),
                  icon: UserPlus,
                  primary: false,
              }
            : null,
        can('reports.view')
            ? {
                  label: 'Ver reportes',
                  href: reportsIndex().url,
                  icon: BarChart3,
                  primary: false,
              }
            : null,
        can('imports.run')
            ? {
                  label: 'Importar historial',
                  href: importsIndex().url,
                  icon: FileUp,
                  primary: false,
              }
            : null,
    ].filter((l) => l !== null),
);

/* ---------- Actividad reciente ---------- */
const relative = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });

function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return 'hace un momento';
}

const initial = (name: string): string => name.trim().charAt(0).toUpperCase();
</script>

<template>
    <Head title="Panel" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="space-y-1">
                <h1 class="text-2xl font-bold tracking-tight">
                    Hola, {{ firstName }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        canSeeAll
                            ? 'Este es el resumen académico de Horus Group.'
                            : 'Este es el resumen de las ediciones que dictas.'
                    }}
                </p>
            </div>
            <span
                class="w-fit rounded-full bg-accent px-3.5 py-1.5 text-xs font-medium text-accent-foreground"
                >{{ today }}</span
            >
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]">
            <!-- Columna principal -->
            <div class="flex min-w-0 flex-col gap-6">
                <dl class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                    <div
                        v-for="card in cards"
                        :key="card.label"
                        class="rounded-xl border p-5"
                    >
                        <dt class="text-sm font-medium text-muted-foreground">
                            {{ card.label }}
                        </dt>
                        <div class="mt-3 flex items-center gap-3">
                            <span
                                class="flex size-12 shrink-0 items-center justify-center rounded-full"
                                :class="card.tone"
                            >
                                <component :is="card.icon" class="size-6" />
                            </span>
                            <dd class="text-3xl font-bold tabular-nums">
                                {{ card.value }}
                            </dd>
                        </div>
                        <p
                            v-if="card.trend"
                            class="mt-3 flex items-center gap-1.5 text-xs font-medium"
                            :class="trendClass[card.trend.tone]"
                        >
                            <TrendingUp
                                v-if="card.trend.tone === 'up'"
                                class="size-4"
                            />
                            <TrendingDown
                                v-else-if="card.trend.tone === 'down'"
                                class="size-4"
                            />
                            {{ card.trend.text }}
                        </p>
                    </div>
                </dl>

                <div
                    class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]"
                >
                    <section class="rounded-xl border p-5">
                        <h2 class="mb-1 text-base font-semibold">
                            Resumen del año {{ year }}
                        </h2>
                        <p class="mb-4 text-xs text-muted-foreground">
                            Matrículas y certificados emitidos mes a mes.
                        </p>
                        <MonthlyChart v-if="hasMonthlyData" :data="monthly" />
                        <EmptyState
                            v-else
                            title="Aún no hay movimiento este año"
                        />
                    </section>

                    <section class="rounded-xl border p-5">
                        <h2 class="mb-4 text-base font-semibold">
                            Matrículas por estado
                        </h2>
                        <DonutChart
                            :segments="donutSegments"
                            center-label="matrículas"
                        />
                    </section>
                </div>

                <section class="rounded-xl border">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 p-5 pb-3"
                    >
                        <h2 class="text-base font-semibold">
                            Ediciones recientes
                        </h2>
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="can('reports.view')"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <a :href="exportMethod().url">
                                    <Download /> Exportar
                                </a>
                            </Button>
                            <Button
                                v-if="
                                    canAny(['editions.view', 'editions.manage'])
                                "
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <Link :href="editionsIndex().url"
                                    >Ver todas</Link
                                >
                            </Button>
                        </div>
                    </div>

                    <div v-if="recentEditions.length === 0" class="p-5 pt-0">
                        <EmptyState title="Aún no hay ediciones" />
                    </div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left text-muted-foreground">
                                <tr>
                                    <th class="px-5 py-3 font-medium">Curso</th>
                                    <th class="px-5 py-3 font-medium">
                                        Periodo
                                    </th>
                                    <th class="px-5 py-3 font-medium">
                                        Ponente
                                    </th>
                                    <th
                                        class="px-5 py-3 text-right font-medium"
                                    >
                                        Matriculados
                                    </th>
                                    <th
                                        class="px-5 py-3 text-right font-medium"
                                    >
                                        Aprobación
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="e in recentEditions"
                                    :key="e.id"
                                    class="border-t"
                                >
                                    <td class="px-5 py-3">
                                        <Link
                                            :href="
                                                CourseEditionController.show.url(
                                                    e.id,
                                                )
                                            "
                                            class="font-medium hover:underline"
                                            >{{ e.course }}</Link
                                        >
                                        <div
                                            class="font-mono text-xs text-muted-foreground"
                                        >
                                            {{ e.code }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        {{
                                            formatPeriod(
                                                e.start_date,
                                                e.end_date,
                                                e.year,
                                            )
                                        }}
                                    </td>
                                    <td class="px-5 py-3 text-muted-foreground">
                                        {{ e.teacher ?? 'Sin asignar' }}
                                    </td>
                                    <td
                                        class="px-5 py-3 text-right tabular-nums"
                                    >
                                        {{ e.participants_count }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <span
                                            v-if="e.approval_rate !== null"
                                            class="inline-flex min-w-14 justify-center rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-700 tabular-nums dark:text-emerald-300"
                                            >{{ e.approval_rate }} %</span
                                        >
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                            >Sin notas</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- Columna derecha: accesos rápidos y actividad -->
            <aside class="flex flex-col gap-6">
                <section
                    v-if="quickLinks.length"
                    class="relative overflow-hidden rounded-xl bg-linear-to-br from-brand to-brand-deep p-5 text-white shadow-md"
                >
                    <img
                        src="/images/horus-logo.png"
                        alt=""
                        class="relative mb-4 size-14 rounded-2xl bg-white object-contain p-2"
                    />
                    <h2 class="relative text-lg font-semibold">
                        Accesos rápidos
                    </h2>
                    <p class="relative mt-1 mb-4 text-sm text-white/75">
                        Lo que más se usa, a un clic.
                    </p>
                    <ul class="relative space-y-2">
                        <li v-for="l in quickLinks" :key="l.label">
                            <Link
                                :href="l.href"
                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                                :class="
                                    l.primary
                                        ? 'bg-amber-soft text-brand-deep hover:bg-amber-soft/85'
                                        : 'bg-white/10 hover:bg-white/20'
                                "
                            >
                                <component :is="l.icon" class="size-4" />
                                {{ l.label }}
                            </Link>
                        </li>
                    </ul>
                </section>

                <section class="rounded-xl border p-5">
                    <h2 class="mb-3 text-base font-semibold">
                        Actividad reciente
                    </h2>
                    <EmptyState
                        v-if="recentEnrollments.length === 0"
                        title="Aún no hay matrículas"
                    />
                    <ul v-else class="divide-y">
                        <li
                            v-for="a in recentEnrollments"
                            :key="a.id"
                            class="flex items-start gap-3 py-3 text-sm"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/12 text-sm font-semibold text-primary"
                                >{{ initial(a.student) }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <p class="leading-snug">
                                    <span class="font-semibold">{{
                                        a.student
                                    }}</span>
                                    {{
                                        a.role === 'ponente'
                                            ? 'se registró como ponente en'
                                            : 'se matriculó en'
                                    }}
                                    {{ a.course }}
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    {{ timeAgo(a.created_at) }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </div>
</template>

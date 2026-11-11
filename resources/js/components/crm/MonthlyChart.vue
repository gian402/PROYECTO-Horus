<script setup lang="ts">
import { computed } from 'vue';

export type MonthlyPoint = {
    label: string;
    enrollments: number;
    certificates: number;
};

const props = defineProps<{ data: MonthlyPoint[] }>();

// Gráfico propio en SVG (sin librerías): barras = matrículas, línea = certificados.
const W = 480;
const H = 230;
const pad = { left: 30, right: 6, top: 10, bottom: 26 };
const innerW = W - pad.left - pad.right;
const innerH = H - pad.top - pad.bottom;

const slot = computed(() => innerW / Math.max(props.data.length, 1));

// Máximo "redondo" para que las 4 divisiones horizontales sean números enteros.
const niceMax = computed(() => {
    const peak = Math.max(
        1,
        ...props.data.flatMap((d) => [d.enrollments, d.certificates]),
    );

    return Math.max(4, Math.ceil(peak / 4) * 4);
});

const ticks = computed(() =>
    [0, 1, 2, 3, 4].map((i) => {
        const value = (niceMax.value / 4) * i;

        return {
            value,
            y: pad.top + innerH - (value / niceMax.value) * innerH,
        };
    }),
);

const yFor = (value: number): number =>
    pad.top + innerH - (value / niceMax.value) * innerH;

const bars = computed(() =>
    props.data.map((d, i) => {
        const width = slot.value * 0.5;
        const height = (d.enrollments / niceMax.value) * innerH;

        return {
            key: d.label,
            x: pad.left + i * slot.value + (slot.value - width) / 2,
            y: pad.top + innerH - height,
            width,
            height,
            title: `${d.label}: ${d.enrollments} matrículas, ${d.certificates} certificados`,
        };
    }),
);

const points = computed(() =>
    props.data.map((d, i) => ({
        x: pad.left + i * slot.value + slot.value / 2,
        y: yFor(d.certificates),
    })),
);

const linePath = computed(() =>
    points.value
        .map(
            (p, i) =>
                `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`,
        )
        .join(' '),
);

const summary = computed(() => {
    const enrollments = props.data.reduce((s, d) => s + d.enrollments, 0);
    const certificates = props.data.reduce((s, d) => s + d.certificates, 0);

    return `Matrículas y certificados por mes: ${enrollments} matrículas y ${certificates} certificados en el año.`;
});
</script>

<template>
    <div>
        <svg
            :viewBox="`0 0 ${W} ${H}`"
            class="h-auto w-full"
            role="img"
            :aria-label="summary"
        >
            <g class="text-border">
                <line
                    v-for="t in ticks"
                    :key="t.value"
                    :x1="pad.left"
                    :x2="W - pad.right"
                    :y1="t.y"
                    :y2="t.y"
                    stroke="currentColor"
                    stroke-dasharray="3 4"
                />
            </g>
            <g class="fill-muted-foreground text-[11px]">
                <text
                    v-for="t in ticks"
                    :key="`l${t.value}`"
                    :x="pad.left - 6"
                    :y="t.y + 4"
                    text-anchor="end"
                >
                    {{ t.value }}
                </text>
                <text
                    v-for="(d, i) in data"
                    :key="`m${d.label}`"
                    :x="pad.left + i * slot + slot / 2"
                    :y="H - 8"
                    text-anchor="middle"
                >
                    {{ d.label }}
                </text>
            </g>

            <rect
                v-for="b in bars"
                :key="b.key"
                :x="b.x"
                :y="b.y"
                :width="b.width"
                :height="Math.max(b.height, 0)"
                rx="4"
                class="fill-primary/85"
            >
                <title>{{ b.title }}</title>
            </rect>

            <path
                :d="linePath"
                fill="none"
                stroke="oklch(0.74 0.16 78)"
                stroke-width="2.5"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
            <circle
                v-for="(p, i) in points"
                :key="`p${i}`"
                :cx="p.x"
                :cy="p.y"
                r="3.5"
                fill="oklch(0.74 0.16 78)"
                class="stroke-card"
                stroke-width="1.5"
            />
        </svg>

        <ul
            class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-muted-foreground"
        >
            <li class="flex items-center gap-2">
                <span class="size-2.5 rounded-sm bg-primary/85" />
                Matrículas
            </li>
            <li class="flex items-center gap-2">
                <span
                    class="h-0.5 w-4 rounded-full"
                    style="background: oklch(0.74 0.16 78)"
                />
                Certificados emitidos
            </li>
        </ul>
    </div>
</template>

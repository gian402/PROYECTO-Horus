<script setup lang="ts">
import { computed } from 'vue';

export type DonutSegment = { label: string; value: number; color: string };

const props = defineProps<{
    segments: DonutSegment[];
    centerLabel?: string;
}>();

// Gráfico de anillo propio en SVG (sin librerías).
const R = 52;
const C = 2 * Math.PI * R;
const GAP = 2;

const total = computed(() =>
    props.segments.reduce((sum, s) => sum + s.value, 0),
);

const arcs = computed(() => {
    let offset = 0;
    const visible = props.segments.filter((s) => s.value > 0).length;

    return props.segments.map((s) => {
        const length = total.value ? (s.value / total.value) * C : 0;
        const drawn = visible > 1 ? Math.max(length - GAP, 0) : length;
        const arc = {
            ...s,
            dash: `${drawn} ${C - drawn}`,
            offset: -offset,
            pct: total.value ? Math.round((s.value / total.value) * 100) : 0,
        };

        offset += length;

        return arc;
    });
});

const summary = computed(() =>
    props.segments.map((s) => `${s.label}: ${s.value}`).join(', '),
);
</script>

<template>
    <div class="flex flex-col items-center gap-5">
        <div class="relative size-44">
            <svg
                viewBox="0 0 140 140"
                class="size-full -rotate-90"
                role="img"
                :aria-label="`Reparto de matrículas por estado. ${summary}`"
            >
                <circle
                    cx="70"
                    cy="70"
                    :r="R"
                    fill="none"
                    stroke-width="16"
                    class="stroke-muted"
                />
                <circle
                    v-for="a in arcs"
                    :key="a.label"
                    cx="70"
                    cy="70"
                    :r="R"
                    fill="none"
                    stroke-width="16"
                    :stroke="a.color"
                    :stroke-dasharray="a.dash"
                    :stroke-dashoffset="a.offset"
                />
            </svg>
            <div
                class="absolute inset-0 flex flex-col items-center justify-center"
            >
                <span class="text-3xl font-bold tabular-nums">{{ total }}</span>
                <span class="text-xs text-muted-foreground">{{
                    centerLabel ?? 'en total'
                }}</span>
            </div>
        </div>

        <ul class="grid w-full gap-2 text-sm">
            <li
                v-for="a in arcs"
                :key="a.label"
                class="flex items-center justify-between gap-3"
            >
                <span class="flex items-center gap-2">
                    <span
                        class="size-2.5 rounded-full"
                        :style="{ background: a.color }"
                    />
                    {{ a.label }}
                </span>
                <span class="text-muted-foreground tabular-nums"
                    >{{ a.value }} · {{ a.pct }} %</span
                >
            </li>
        </ul>
    </div>
</template>

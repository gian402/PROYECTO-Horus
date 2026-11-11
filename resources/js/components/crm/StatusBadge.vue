<script setup lang="ts">
import { computed } from 'vue';
import type { EnrollmentRole, EnrollmentStatus } from '@/types';

// Con role='ponente' se muestra "Ponente": no se califica y siempre está aprobado.
const props = defineProps<{
    status: EnrollmentStatus;
    role?: EnrollmentRole;
}>();

const config = computed(
    () =>
        ({
            cursando: {
                label: 'Cursando',
                class: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200',
            },
            aprobado: {
                label: 'Aprobado',
                class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
            },
            desaprobado: {
                label: 'Desaprobado',
                class: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
            },
            ponente: {
                label: 'Ponente',
                class: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
            },
        })[props.role === 'ponente' ? 'ponente' : props.status],
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
        :class="config.class"
        >{{ config.label }}</span
    >
</template>

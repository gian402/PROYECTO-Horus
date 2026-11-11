<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        placeholder?: string;
        /** Otros parámetros de la URL que deben conservarse al buscar. */
        params?: Record<string, string | number | null | undefined>;
    }>(),
    { modelValue: '', placeholder: 'Buscar…', params: () => ({}) },
);

const term = ref(props.modelValue);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(term, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            window.location.pathname,
            { ...props.params, search: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="relative w-full max-w-sm">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <Input
            v-model="term"
            type="search"
            class="pl-9"
            :placeholder="placeholder"
            :aria-label="placeholder"
        />
    </div>
</template>

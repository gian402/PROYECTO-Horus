<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { Paginated } from '@/types';

const props = defineProps<{ paginator: Paginated<unknown> }>();

const links = computed(() =>
    props.paginator.links.map((link) => ({
        ...link,
        label: link.label
            .replace('&laquo;', '«')
            .replace('&raquo;', '»')
            .replace('Previous', 'Anterior')
            .replace('Next', 'Siguiente')
            .replace('pagination.previous', 'Anterior')
            .replace('pagination.next', 'Siguiente'),
    })),
);
</script>

<template>
    <nav
        v-if="paginator.last_page > 1 || paginator.total > 0"
        class="flex flex-col items-center justify-between gap-3 text-sm sm:flex-row"
        aria-label="Paginación"
    >
        <p class="text-muted-foreground">
            Mostrando {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} de
            {{ paginator.total }}
        </p>
        <ul v-if="paginator.last_page > 1" class="flex flex-wrap gap-1">
            <li v-for="(link, i) in links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2"
                    :class="
                        link.active
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                    :aria-current="link.active ? 'page' : undefined"
                    >{{ link.label }}</Link
                >
                <span
                    v-else
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-muted-foreground opacity-50"
                    >{{ link.label }}</span
                >
            </li>
        </ul>
    </nav>
</template>

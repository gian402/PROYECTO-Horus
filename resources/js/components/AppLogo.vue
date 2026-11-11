<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';

const name = usePage().props.name;
</script>

<template>
    <!-- Logo de Horus (ojo de Horus) sobre una ficha blanca para que resalte en el azul marino. -->
    <div
        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-white/20 group-data-[collapsible=icon]:size-8"
    >
        <img
            src="/images/horus-logo.png"
            alt="Horus Group"
            class="size-7 object-contain group-data-[collapsible=icon]:size-5"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left leading-tight">
        <span class="truncate text-base font-bold tracking-wide">{{
            name
        }}</span>
        <span class="truncate text-[11px] text-sidebar-foreground/60"
            >Horus Group SRL</span
        >
    </div>
</template>

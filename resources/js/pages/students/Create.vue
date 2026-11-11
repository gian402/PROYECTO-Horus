<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/crm/PageHeader.vue';
import { create, index } from '@/routes/students';
import StudentForm from './StudentForm.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Participantes', href: index() },
            { title: 'Nuevo', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Nuevo participante" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Nuevo participante"
            description="Registra a una persona para poder matricularla en ediciones."
        />
        <StudentForm />
    </div>
</template>

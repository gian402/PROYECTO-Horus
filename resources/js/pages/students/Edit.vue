<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/crm/PageHeader.vue';
import { index } from '@/routes/students';
import type { StudentFormData } from '@/types';
import StudentForm from './StudentForm.vue';

defineProps<{ student: StudentFormData }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Participantes', href: index() }],
    },
});
</script>

<template>
    <Head title="Editar participante" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Editar participante"
            :description="`${student.first_name} ${student.last_name} · ${student.dni}`"
        />
        <StudentForm :student="student" />
    </div>
</template>

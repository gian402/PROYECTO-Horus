<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import StudentController from '@/actions/App/Http/Controllers/StudentController';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import Pagination from '@/components/crm/Pagination.vue';
import SearchInput from '@/components/crm/SearchInput.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/students';
import type { Filters, Paginated, StudentRow } from '@/types';

defineProps<{
    students: Paginated<StudentRow>;
    filters: Filters;
    can: { create: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Participantes', href: index() }],
    },
});
</script>

<template>
    <Head title="Participantes" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Participantes"
            description="Personas registradas por el personal de Horus Group."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="StudentController.create.url()">
                        <Plus /> Nuevo participante
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SearchInput
            :model-value="filters.search ?? ''"
            placeholder="Buscar por DNI, nombres o apellidos"
        />

        <EmptyState
            v-if="students.data.length === 0"
            :title="
                filters.search
                    ? 'No hay participantes que coincidan con la búsqueda'
                    : 'Aún no hay participantes registrados'
            "
        />

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">DNI</th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Apellidos y nombres
                        </th>
                        <th
                            scope="col"
                            class="hidden px-4 py-3 font-medium md:table-cell"
                        >
                            Contacto
                        </th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">
                            Matrículas
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="student in students.data"
                        :key="student.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3 font-mono">{{ student.dni }}</td>
                        <td class="px-4 py-3">
                            <Link
                                :href="StudentController.show.url(student.id)"
                                class="font-medium hover:underline"
                                >{{ student.last_name }},
                                {{ student.first_name }}</Link
                            >
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{ student.email ?? student.phone ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ student.enrollments_count }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="students" />
    </div>
</template>

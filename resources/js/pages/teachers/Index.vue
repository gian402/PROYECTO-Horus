<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import TeacherController from '@/actions/App/Http/Controllers/TeacherController';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import Pagination from '@/components/crm/Pagination.vue';
import SearchInput from '@/components/crm/SearchInput.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/teachers';
import type { Filters, Paginated, TeacherRow } from '@/types';

defineProps<{ teachers: Paginated<TeacherRow>; filters: Filters }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ponentes', href: index() }] },
});

const toDelete = ref<TeacherRow | null>(null);
const deleting = ref(false);

function destroy(): void {
    if (!toDelete.value) {
        return;
    }

    router.delete(TeacherController.destroy.url(toDelete.value.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            toDelete.value = null;
        },
    });
}
</script>

<template>
    <Head title="Ponentes" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Ponentes"
            description="Docentes que dictan las ediciones. La cuenta de acceso de un ponente se vincula en Usuarios."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="TeacherController.create.url()">
                        <Plus /> Nuevo ponente
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SearchInput
            :model-value="filters.search ?? ''"
            placeholder="Buscar por DNI, nombre o especialidad"
        />

        <EmptyState v-if="teachers.data.length === 0" title="No hay ponentes" />

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">DNI</th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Nombre
                        </th>
                        <th
                            scope="col"
                            class="hidden px-4 py-3 font-medium md:table-cell"
                        >
                            Cuenta
                        </th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">
                            Ediciones
                        </th>
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="teacher in teachers.data"
                        :key="teacher.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-mono">{{ teacher.dni }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium">
                                {{ teacher.full_name }}
                            </div>
                            <div class="text-muted-foreground">
                                {{ teacher.specialty ?? '—' }}
                            </div>
                        </td>
                        <td
                            class="hidden px-4 py-3 text-muted-foreground md:table-cell"
                        >
                            {{ teacher.user_email ?? 'Sin cuenta' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ teacher.editions_count }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    as-child
                                >
                                    <Link
                                        :href="
                                            TeacherController.edit.url(
                                                teacher.id,
                                            )
                                        "
                                        :aria-label="`Editar ${teacher.full_name}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive"
                                    :aria-label="`Eliminar ${teacher.full_name}`"
                                    @click="toDelete = teacher"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="teachers" />

        <ConfirmDialog
            :open="toDelete !== null"
            :title="`¿Eliminar a ${toDelete?.full_name ?? ''}?`"
            description="Solo es posible si no está asignado a ninguna edición."
            confirm-label="Eliminar"
            destructive
            :processing="deleting"
            @update:open="(v) => !v && (toDelete = null)"
            @confirm="destroy"
        />
    </div>
</template>

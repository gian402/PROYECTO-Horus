<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import CourseController from '@/actions/App/Http/Controllers/CourseController';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import Pagination from '@/components/crm/Pagination.vue';
import SearchInput from '@/components/crm/SearchInput.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/courses';
import type { CourseRow, Filters, Paginated } from '@/types';

defineProps<{ courses: Paginated<CourseRow>; filters: Filters }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Cursos', href: index() }] },
});

const toDelete = ref<CourseRow | null>(null);
const deleting = ref(false);

function destroy(): void {
    if (!toDelete.value) {
        return;
    }

    router.delete(CourseController.destroy.url(toDelete.value.id), {
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
    <Head title="Cursos" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Cursos"
            description="Catálogo de capacitaciones. El código forma parte de cada código de registro (HGC-CÓDIGO-NN-AAA)."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="CourseController.create.url()">
                        <Plus /> Nuevo curso
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <SearchInput
            :model-value="filters.search ?? ''"
            placeholder="Buscar por código o nombre"
        />

        <EmptyState
            v-if="courses.data.length === 0"
            title="No hay cursos"
            description="Crea el primer curso del catálogo, por ejemplo PA – Primeros Auxilios."
        />

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Código
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Nombre
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
                        v-for="course in courses.data"
                        :key="course.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3 font-mono font-medium">
                            {{ course.code }}
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ course.name }}</div>
                            <div
                                v-if="course.description"
                                class="line-clamp-1 text-muted-foreground"
                            >
                                {{ course.description }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ course.editions_count }}
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
                                            CourseController.edit.url(course.id)
                                        "
                                        :aria-label="`Editar ${course.code}`"
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    class="text-destructive"
                                    :aria-label="`Eliminar ${course.code}`"
                                    @click="toDelete = course"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="courses" />

        <ConfirmDialog
            :open="toDelete !== null"
            :title="`¿Eliminar el curso ${toDelete?.code ?? ''}?`"
            description="Solo es posible si el curso no tiene ediciones."
            confirm-label="Eliminar"
            destructive
            :processing="deleting"
            @update:open="(v) => !v && (toDelete = null)"
            @confirm="destroy"
        />
    </div>
</template>

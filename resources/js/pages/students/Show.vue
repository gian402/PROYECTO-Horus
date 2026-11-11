<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import StudentController from '@/actions/App/Http/Controllers/StudentController';
import ConfirmDialog from '@/components/crm/ConfirmDialog.vue';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/students';
import type { StudentDetail, StudentEnrollment } from '@/types';

const props = defineProps<{
    student: StudentDetail;
    enrollments: StudentEnrollment[];
    can: { update: boolean; delete: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Participantes', href: index() }],
    },
});

const confirmOpen = ref(false);
const deleting = ref(false);

function destroy(): void {
    router.delete(StudentController.destroy.url(props.student.id), {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="student.full_name" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            :title="student.full_name"
            :description="`DNI ${student.dni}`"
        >
            <template #actions>
                <Button v-if="can.update" variant="outline" as-child>
                    <Link :href="StudentController.edit.url(student.id)">
                        <Pencil /> Editar
                    </Link>
                </Button>
                <ConfirmDialog
                    v-if="can.delete"
                    v-model:open="confirmOpen"
                    title="¿Eliminar participante?"
                    description="Solo es posible si no tiene matrículas. Esta acción no se puede deshacer."
                    confirm-label="Eliminar"
                    destructive
                    :processing="deleting"
                    @confirm="destroy"
                >
                    <template #trigger>
                        <Button variant="ghost" class="text-destructive">
                            <Trash2 /> Eliminar
                        </Button>
                    </template>
                </ConfirmDialog>
            </template>
        </PageHeader>

        <dl class="grid gap-4 rounded-xl border p-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-muted-foreground">Correo</dt>
                <dd>{{ student.email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Teléfono</dt>
                <dd>{{ student.phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Matrículas</dt>
                <dd class="tabular-nums">{{ enrollments.length }}</dd>
            </div>
        </dl>

        <section class="flex flex-col gap-3">
            <h2 class="text-base font-semibold">Historial académico</h2>

            <EmptyState
                v-if="enrollments.length === 0"
                title="Sin matrículas"
                description="Las matrículas se registran desde la página de cada edición."
            />

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Código
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Curso / edición
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-right font-medium"
                            >
                                Nota
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Estado
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Certificado
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="e in enrollments"
                            :key="e.id"
                            class="border-t"
                        >
                            <td class="px-4 py-3 font-mono">
                                {{ e.registration_code }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ e.edition.course.name }}
                                </div>
                                <div class="text-muted-foreground">
                                    {{ e.edition.label }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ e.grade ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :status="e.status"
                                    :role="e.role"
                                />
                            </td>
                            <td class="px-4 py-3">
                                {{
                                    e.certificate
                                        ? `Emitido ${e.certificate.issued_at}`
                                        : '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

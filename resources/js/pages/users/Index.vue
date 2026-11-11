<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import EmptyState from '@/components/crm/EmptyState.vue';
import PageHeader from '@/components/crm/PageHeader.vue';
import Pagination from '@/components/crm/Pagination.vue';
import SearchInput from '@/components/crm/SearchInput.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/users';
import type { Filters, Paginated, TeacherOption, UserRow } from '@/types';

const props = defineProps<{
    users: Paginated<UserRow>;
    filters: Filters;
    roles: string[];
    teachers: TeacherOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: index() }],
    },
});

const currentUserId = usePage().props.auth.user.id;

type Draft = { role: string; teacher_id: number | null };

const drafts = reactive<Record<number, Draft>>({});
const errors = ref<Record<number, Record<string, string>>>({});
const savingId = ref<number | null>(null);

function draftFor(user: UserRow): Draft {
    if (!drafts[user.id]) {
        drafts[user.id] = {
            role: user.role ?? '',
            teacher_id: user.teacher?.id ?? null,
        };
    }

    return drafts[user.id];
}

function availableTeachers(user: UserRow): TeacherOption[] {
    return props.teachers.filter(
        (t) => t.user_id === null || t.user_id === user.id,
    );
}

function isDirty(user: UserRow): boolean {
    const d = draftFor(user);

    return (
        d.role !== (user.role ?? '') ||
        (d.role === 'Ponente' && d.teacher_id !== (user.teacher?.id ?? null))
    );
}

function save(user: UserRow): void {
    const d = draftFor(user);

    router.put(
        UserController.update.url(user.id),
        {
            role: d.role,
            teacher_id: d.role === 'Ponente' ? d.teacher_id : null,
        },
        {
            preserveScroll: true,
            onStart: () => (savingId.value = user.id),
            onFinish: () => (savingId.value = null),
            onError: (e) => (errors.value[user.id] = e),
            onSuccess: () => {
                delete errors.value[user.id];
                delete drafts[user.id];
            },
        },
    );
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader
            title="Usuarios"
            description="Personal con acceso al CRM. Asigna el rol de cada cuenta y, para los ponentes, el ponente que les corresponde."
        />

        <SearchInput
            :model-value="filters.search ?? ''"
            placeholder="Buscar por nombre o correo"
        />

        <EmptyState
            v-if="users.data.length === 0"
            title="No hay usuarios que coincidan"
        />

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Usuario
                        </th>
                        <th scope="col" class="px-4 py-3 font-medium">Rol</th>
                        <th scope="col" class="px-4 py-3 font-medium">
                            Ponente vinculado
                        </th>
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">Acciones</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-t align-top"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">
                                {{ user.name }}
                                <span
                                    v-if="user.id === currentUserId"
                                    class="text-xs text-muted-foreground"
                                    >(tú)</span
                                >
                            </div>
                            <div class="text-muted-foreground">
                                {{ user.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <select
                                v-model="draftFor(user).role"
                                class="h-9 rounded-md border bg-background px-2"
                                :aria-label="`Rol de ${user.name}`"
                            >
                                <option value="" disabled>Sin rol</option>
                                <option
                                    v-for="role in roles"
                                    :key="role"
                                    :value="role"
                                >
                                    {{ role }}
                                </option>
                            </select>
                            <InputError
                                class="mt-1"
                                :message="errors[user.id]?.role"
                            />
                        </td>
                        <td class="px-4 py-3">
                            <select
                                v-if="draftFor(user).role === 'Ponente'"
                                v-model="draftFor(user).teacher_id"
                                class="h-9 rounded-md border bg-background px-2"
                                :aria-label="`Ponente vinculado a ${user.name}`"
                            >
                                <option :value="null">— Ninguno —</option>
                                <option
                                    v-for="t in availableTeachers(user)"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.full_name }}
                                </option>
                            </select>
                            <span v-else class="text-muted-foreground">—</span>
                            <InputError
                                class="mt-1"
                                :message="errors[user.id]?.teacher_id"
                            />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                size="sm"
                                :disabled="
                                    !isDirty(user) ||
                                    !draftFor(user).role ||
                                    savingId === user.id
                                "
                                @click="save(user)"
                                >Guardar</Button
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :paginator="users" />
    </div>
</template>

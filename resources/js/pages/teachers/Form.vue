<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import TeacherController from '@/actions/App/Http/Controllers/TeacherController';
import PageHeader from '@/components/crm/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/teachers';
import type { TeacherFormData } from '@/types';

const props = defineProps<{ teacher: TeacherFormData | null }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ponentes', href: index() }] },
});

const action = computed(() =>
    props.teacher
        ? TeacherController.update.form(props.teacher.id)
        : TeacherController.store.form(),
);
const title = computed(() =>
    props.teacher ? 'Editar ponente' : 'Nuevo ponente',
);
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="title" />

        <Form
            v-bind="action"
            class="grid max-w-2xl gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2 sm:max-w-xs">
                <Label for="dni">DNI / documento</Label>
                <Input
                    id="dni"
                    name="dni"
                    :default-value="teacher?.dni"
                    required
                    maxlength="15"
                    class="uppercase"
                    autocomplete="off"
                    v-focus
                />
                <InputError :message="errors.dni" />
            </div>

            <div class="grid gap-2">
                <Label for="full_name">Nombre completo</Label>
                <Input
                    id="full_name"
                    name="full_name"
                    :default-value="teacher?.full_name"
                    required
                    maxlength="150"
                />
                <InputError :message="errors.full_name" />
            </div>

            <div class="grid gap-2">
                <Label for="specialty">Especialidad (opcional)</Label>
                <Input
                    id="specialty"
                    name="specialty"
                    :default-value="teacher?.specialty ?? ''"
                    maxlength="150"
                    placeholder="Ej. Enfermería"
                />
                <InputError :message="errors.specialty" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    {{ teacher ? 'Guardar cambios' : 'Registrar ponente' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="TeacherController.index.url()">Cancelar</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

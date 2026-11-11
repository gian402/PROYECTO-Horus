<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CourseController from '@/actions/App/Http/Controllers/CourseController';
import PageHeader from '@/components/crm/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/courses';
import type { CourseFormData } from '@/types';

const props = defineProps<{ course: CourseFormData | null }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Cursos', href: index() }] },
});

const action = computed(() =>
    props.course
        ? CourseController.update.form(props.course.id)
        : CourseController.store.form(),
);
const title = computed(() =>
    props.course ? `Editar curso ${props.course.code}` : 'Nuevo curso',
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
                <Label for="code">Código</Label>
                <Input
                    id="code"
                    name="code"
                    :default-value="course?.code"
                    required
                    maxlength="10"
                    class="font-mono uppercase"
                    placeholder="Ej. PA"
                    :readonly="course?.code_locked"
                    :aria-describedby="
                        course?.code_locked ? 'code-locked' : undefined
                    "
                    v-focus
                />
                <p
                    v-if="course?.code_locked"
                    id="code-locked"
                    class="text-xs text-muted-foreground"
                >
                    No se puede cambiar: el curso ya tiene ediciones y códigos
                    de registro emitidos.
                </p>
                <InputError :message="errors.code" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="course?.name"
                    required
                    maxlength="150"
                    placeholder="Ej. Primeros Auxilios"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Descripción (opcional)</Label>
                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    maxlength="2000"
                    class="rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :value="course?.description ?? ''"
                />
                <InputError :message="errors.description" />
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    {{ course ? 'Guardar cambios' : 'Crear curso' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="CourseController.index.url()">Cancelar</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

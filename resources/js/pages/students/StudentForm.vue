<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudentController from '@/actions/App/Http/Controllers/StudentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { StudentFormData } from '@/types';

const props = defineProps<{ student?: StudentFormData }>();

const action = computed(() =>
    props.student?.id
        ? StudentController.update.form(props.student.id)
        : StudentController.store.form(),
);

const cancelHref = computed(() =>
    props.student?.id
        ? StudentController.show.url(props.student.id)
        : StudentController.index.url(),
);
</script>

<template>
    <Form
        v-bind="action"
        class="grid max-w-2xl gap-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="dni">DNI / documento</Label>
            <Input
                id="dni"
                name="dni"
                :default-value="student?.dni"
                required
                maxlength="15"
                autocomplete="off"
                class="uppercase"
                placeholder="Ej. 70123456"
                v-focus
            />
            <InputError :message="errors.dni" />
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="first_name">Nombres</Label>
                <Input
                    id="first_name"
                    name="first_name"
                    :default-value="student?.first_name"
                    required
                    maxlength="100"
                    autocomplete="given-name"
                />
                <InputError :message="errors.first_name" />
            </div>
            <div class="grid gap-2">
                <Label for="last_name">Apellidos</Label>
                <Input
                    id="last_name"
                    name="last_name"
                    :default-value="student?.last_name"
                    required
                    maxlength="100"
                    autocomplete="family-name"
                />
                <InputError :message="errors.last_name" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="email">Correo (opcional)</Label>
                <Input
                    id="email"
                    name="email"
                    type="email"
                    :default-value="student?.email ?? ''"
                    autocomplete="email"
                />
                <InputError :message="errors.email" />
            </div>
            <div class="grid gap-2">
                <Label for="phone">Teléfono (opcional)</Label>
                <Input
                    id="phone"
                    name="phone"
                    type="tel"
                    :default-value="student?.phone ?? ''"
                    autocomplete="tel"
                />
                <InputError :message="errors.phone" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ student?.id ? 'Guardar cambios' : 'Registrar participante' }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="cancelHref">Cancelar</Link>
            </Button>
        </div>
    </Form>
</template>

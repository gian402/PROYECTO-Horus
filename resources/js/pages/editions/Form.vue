<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CourseEditionController from '@/actions/App/Http/Controllers/CourseEditionController';
import PageHeader from '@/components/crm/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/editions';
import type { EditionFormData, EditionOptions } from '@/types';

const props = defineProps<{
    edition: EditionFormData | null;
    options: EditionOptions;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Ediciones', href: index() }] },
});

const action = computed(() =>
    props.edition
        ? CourseEditionController.update.form(props.edition.id)
        : CourseEditionController.store.form(),
);

const startDate = ref(props.edition?.start_date ?? '');
// Ediciones históricas importadas sin fechas: pueden quedar sin fechas.
const datesOptional = computed(
    () => !!props.edition && props.edition.start_date === null,
);
const year = ref<number | ''>(props.edition?.year ?? '');

// Sugiere el año a partir de la fecha de inicio (se puede editar).
function onStartChange(): void {
    if (!props.edition?.locked && startDate.value) {
        year.value = Number(startDate.value.slice(0, 4));
    }
}

const selectClass =
    'h-9 w-full rounded-md border bg-background px-3 text-sm disabled:opacity-60';
const cancelHref = computed(() =>
    props.edition
        ? CourseEditionController.show.url(props.edition.id)
        : CourseEditionController.index.url(),
);
</script>

<template>
    <Head :title="edition ? 'Editar edición' : 'Nueva edición'" />

    <div class="flex flex-col gap-6 p-4">
        <PageHeader :title="edition ? 'Editar edición' : 'Nueva edición'" />

        <p
            v-if="edition?.locked"
            class="max-w-2xl rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
        >
            La edición ya tiene matrículas: el curso y el año no se pueden
            cambiar porque forman parte de los códigos de registro asignados.
        </p>

        <Form
            v-bind="action"
            class="grid max-w-2xl gap-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="course_id">Curso</Label>
                <select
                    id="course_id"
                    name="course_id"
                    required
                    :class="selectClass"
                    :disabled="edition?.locked"
                >
                    <option value="" disabled :selected="!edition">
                        Selecciona un curso
                    </option>
                    <option
                        v-for="c in options.courses"
                        :key="c.id"
                        :value="c.id"
                        :selected="edition?.course_id === c.id"
                    >
                        {{ c.code }} – {{ c.name }}
                    </option>
                </select>
                <!-- Un select deshabilitado no se envía: mantener el valor. -->
                <input
                    v-if="edition?.locked"
                    type="hidden"
                    name="course_id"
                    :value="edition.course_id"
                />
                <InputError :message="errors.course_id" />
            </div>

            <div class="grid gap-2">
                <Label for="teacher_id">Ponente (opcional)</Label>
                <select id="teacher_id" name="teacher_id" :class="selectClass">
                    <option value="" :selected="!edition?.teacher_id">
                        Sin asignar
                    </option>
                    <option
                        v-for="t in options.teachers"
                        :key="t.id"
                        :value="t.id"
                        :selected="edition?.teacher_id === t.id"
                    >
                        {{ t.full_name }}
                    </option>
                </select>
                <InputError :message="errors.teacher_id" />
            </div>

            <p v-if="datesOptional" class="text-sm text-muted-foreground">
                Edición histórica importada sin fechas: puedes completarlas
                (ambas) o dejarlas vacías.
            </p>
            <div class="grid gap-6 sm:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="start_date">Inicio</Label>
                    <Input
                        id="start_date"
                        v-model="startDate"
                        name="start_date"
                        type="date"
                        :required="!datesOptional"
                        @change="onStartChange"
                    />
                    <InputError :message="errors.start_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="end_date">Fin</Label>
                    <Input
                        id="end_date"
                        name="end_date"
                        type="date"
                        :required="!datesOptional"
                        :min="startDate || undefined"
                        :default-value="edition?.end_date ?? undefined"
                    />
                    <InputError :message="errors.end_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="year">Año académico</Label>
                    <Input
                        id="year"
                        v-model="year"
                        name="year"
                        type="number"
                        min="2000"
                        max="2100"
                        required
                        :readonly="edition?.locked"
                    />
                    <InputError :message="errors.year" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    {{ edition ? 'Guardar cambios' : 'Crear edición' }}
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="cancelHref">Cancelar</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>

<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { authenticate } from '@/routes/portal';
</script>

<template>
    <Head title="Mi cuenta">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <main
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-linear-to-br from-[oklch(0.5_0.2_263)] via-[oklch(0.62_0.17_252)] to-[oklch(0.82_0.1_235)] p-4 dark:from-[oklch(0.17_0.05_265)] dark:via-[oklch(0.22_0.07_265)] dark:to-[oklch(0.3_0.09_262)]"
    >
        <div
            class="relative w-full max-w-md space-y-6 rounded-[2rem] bg-card p-6 shadow-2xl shadow-black/20 sm:p-8"
        >
            <div class="flex items-center gap-3">
                <img
                    src="/images/horus-logo.png"
                    alt="Horus Group"
                    class="h-12 w-auto"
                />
                <div class="leading-tight">
                    <p class="text-lg font-bold text-primary">
                        Horus Group SRL
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Consulta tu matrícula y certificado
                    </p>
                </div>
            </div>

            <Form
                v-bind="authenticate.form()"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-5"
            >
                <div class="grid gap-2">
                    <Label for="dni">DNI</Label>
                    <Input
                        id="dni"
                        type="text"
                        name="dni"
                        required
                        v-focus
                        autocomplete="off"
                        placeholder="Tu número de DNI"
                    />
                    <InputError :message="errors.dni" />
                </div>

                <div class="grid gap-2">
                    <Label for="registration_code">
                        Código de registro
                    </Label>
                    <Input
                        id="registration_code"
                        type="text"
                        name="registration_code"
                        required
                        autocomplete="off"
                        placeholder="Ej. HGC-SM-12-024"
                    />
                    <p class="text-xs text-muted-foreground">
                        Es el código que te entregamos al matricularte;
                        también aparece en tu certificado.
                    </p>
                    <InputError :message="errors.registration_code" />
                </div>

                <Button type="submit" class="mt-2 w-full" :disabled="processing">
                    <Spinner v-if="processing" />
                    Consultar mi matrícula
                </Button>
            </Form>
        </div>
    </main>
</template>

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { home } from '@/routes';

const name = usePage().props.name;
const year = new Date().getFullYear();

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh items-center justify-center overflow-hidden bg-linear-to-br from-[oklch(0.5_0.2_263)] via-[oklch(0.62_0.17_252)] to-[oklch(0.82_0.1_235)] p-4 sm:p-8 dark:from-[oklch(0.17_0.05_265)] dark:via-[oklch(0.22_0.07_265)] dark:to-[oklch(0.3_0.09_262)]"
    >
        <!-- Arcos suaves de fondo -->
        <div
            class="pointer-events-none absolute -top-40 -left-32 size-[34rem] rounded-full border border-white/15"
        />
        <div
            class="pointer-events-none absolute -top-24 -left-16 size-[26rem] rounded-full border border-white/10"
        />

        <div
            class="relative grid min-h-[40rem] w-full max-w-6xl overflow-hidden rounded-[2.5rem] bg-card shadow-2xl shadow-black/20 lg:grid-cols-[1.05fr_1fr]"
        >
            <!-- Panel de marca (solo en pantallas grandes) -->
            <div
                class="relative m-4 hidden flex-col overflow-hidden rounded-[2rem] bg-linear-to-br from-brand to-brand-deep p-10 text-white lg:flex"
            >
                <div class="relative z-10 max-w-sm space-y-4">
                    <h2 class="text-4xl leading-[1.1] font-bold tracking-tight">
                        Registro académico de Horus Group
                    </h2>
                    <p class="text-sm leading-relaxed text-white/80">
                        Cursos, ediciones, matrículas, notas y certificados en
                        un solo lugar.
                    </p>
                </div>

                <!-- El ojo de Horus sobre el sol: ocupa el lugar de la foto de la referencia. -->
                <div
                    class="relative mt-auto flex flex-1 items-end justify-center pt-8"
                >
                    <div
                        class="absolute bottom-0 size-[21rem] translate-y-1/4 rounded-full bg-amber-soft"
                    />
                    <div
                        class="absolute bottom-0 size-[26rem] translate-y-1/4 rounded-full border-2 border-amber-soft/40"
                    />
                    <img
                        src="/images/horus-logo.png"
                        alt="Ojo de Horus"
                        class="relative mb-2 w-[22rem] max-w-none drop-shadow-xl"
                    />
                </div>
            </div>

            <!-- Formulario -->
            <div class="flex flex-col px-6 py-10 sm:px-14">
                <div
                    class="mx-auto my-auto flex w-full max-w-md flex-col gap-8"
                >
                    <div class="flex flex-col gap-7">
                        <Link
                            :href="home()"
                            class="flex items-center gap-3 font-bold text-primary"
                        >
                            <img
                                src="/images/horus-logo.png"
                                alt=""
                                class="h-11 w-auto"
                            />
                            <span class="text-xl tracking-wide uppercase">{{
                                name
                            }}</span>
                        </Link>
                        <div class="space-y-2">
                            <h1
                                v-if="title"
                                class="text-3xl font-bold tracking-tight"
                            >
                                {{ title }}
                            </h1>
                            <p
                                v-if="description"
                                class="text-sm text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </div>
                    </div>

                    <!-- Campos y botón más amplios, como en la referencia (ver app.css). -->
                    <div class="auth-form">
                        <slot />
                    </div>
                </div>

                <p class="pt-8 text-center text-xs text-muted-foreground">
                    © {{ year }} Horus Group SRL. Uso interno.
                </p>
            </div>
        </div>
    </div>
</template>

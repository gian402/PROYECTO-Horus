<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, CircleHelp, CircleX } from '@lucide/vue';
import { computed } from 'vue';
import { formatDate, formatPeriod } from '@/lib/format';
import type { VerificationStatus, VerifiedCertificate } from '@/types';

const props = defineProps<{
    status: VerificationStatus;
    certificate: VerifiedCertificate | null;
}>();

const view = computed(
    () =>
        ({
            valid: {
                icon: CircleCheck,
                title: 'Certificado válido',
                text: 'Este certificado fue emitido por Horus Group SRL y se encuentra vigente.',
                tone: 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100',
            },
            revoked: {
                icon: CircleX,
                title: 'Certificado anulado',
                text: 'Este certificado fue emitido por Horus Group SRL, pero ha sido anulado y ya no tiene validez.',
                tone: 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-100',
            },
            not_found: {
                icon: CircleHelp,
                title: 'Certificado no encontrado',
                text: 'No existe ningún certificado con este código de verificación. Revisa que el enlace o el código QR sean correctos.',
                tone: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
            },
        })[props.status],
);
</script>

<template>
    <Head :title="view.title">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <main
        class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden bg-linear-to-br from-[oklch(0.5_0.2_263)] via-[oklch(0.62_0.17_252)] to-[oklch(0.82_0.1_235)] p-4 dark:from-[oklch(0.17_0.05_265)] dark:via-[oklch(0.22_0.07_265)] dark:to-[oklch(0.3_0.09_262)]"
    >
        <div
            class="relative w-full max-w-lg space-y-5 rounded-[2rem] bg-card p-6 shadow-2xl shadow-black/20 sm:p-8"
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
                        Verificación de certificados
                    </p>
                </div>
            </div>

            <section
                class="rounded-2xl border p-5"
                :class="view.tone"
                role="status"
                aria-live="polite"
            >
                <div class="flex items-start gap-4">
                    <component :is="view.icon" class="size-10 shrink-0" />
                    <div>
                        <h1 class="text-xl font-bold">{{ view.title }}</h1>
                        <p class="mt-1 text-sm">{{ view.text }}</p>
                    </div>
                </div>
            </section>

            <dl
                v-if="certificate"
                class="grid gap-4 rounded-2xl bg-muted/60 p-5 text-sm"
            >
                <div>
                    <dt class="text-muted-foreground">
                        {{
                            certificate.role === 'ponente'
                                ? 'Ponente'
                                : 'Participante'
                        }}
                    </dt>
                    <dd class="text-base font-semibold">
                        {{ certificate.participant }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Capacitación</dt>
                    <dd class="font-semibold">{{ certificate.course }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Periodo</dt>
                    <dd class="font-medium">
                        {{
                            formatPeriod(
                                certificate.start_date,
                                certificate.end_date,
                                certificate.year,
                            )
                        }}
                    </dd>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-muted-foreground">
                            Código de registro
                        </dt>
                        <dd class="font-mono text-xs font-medium sm:text-sm">
                            {{ certificate.registration_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Fecha de emisión</dt>
                        <dd class="font-medium">
                            {{ formatDate(certificate.issued_at) }}
                        </dd>
                    </div>
                </div>
                <div v-if="certificate.revoked_at">
                    <dt class="text-muted-foreground">Fecha de anulación</dt>
                    <dd class="font-medium">
                        {{ formatDate(certificate.revoked_at) }}
                    </dd>
                </div>
            </dl>
        </div>
    </main>
</template>

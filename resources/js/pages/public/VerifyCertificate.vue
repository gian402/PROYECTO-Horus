<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CircleCheck, CircleHelp, CircleX } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
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
        class="flex min-h-svh flex-col items-center justify-center bg-muted/40 p-4"
    >
        <div class="w-full max-w-lg space-y-6">
            <div class="flex items-center justify-center gap-2">
                <div
                    class="flex size-9 items-center justify-center rounded-md bg-primary text-primary-foreground"
                >
                    <AppLogoIcon class="size-5 fill-current" />
                </div>
                <span class="text-lg font-semibold">Horus Group SRL</span>
            </div>

            <section
                class="rounded-xl border p-5"
                :class="view.tone"
                role="status"
                aria-live="polite"
            >
                <div class="flex items-start gap-3">
                    <component :is="view.icon" class="mt-0.5 size-6 shrink-0" />
                    <div>
                        <h1 class="text-lg font-semibold">{{ view.title }}</h1>
                        <p class="mt-1 text-sm">{{ view.text }}</p>
                    </div>
                </div>
            </section>

            <dl
                v-if="certificate"
                class="grid gap-3 rounded-xl border bg-background p-5 text-sm"
            >
                <div>
                    <dt class="text-muted-foreground">
                        {{
                            certificate.role === 'ponente'
                                ? 'Ponente'
                                : 'Participante'
                        }}
                    </dt>
                    <dd class="font-medium">{{ certificate.participant }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Capacitación</dt>
                    <dd class="font-medium">{{ certificate.course }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Periodo</dt>
                    <dd>
                        {{
                            formatPeriod(
                                certificate.start_date,
                                certificate.end_date,
                                certificate.year,
                            )
                        }}
                    </dd>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <dt class="text-muted-foreground">
                            Código de registro
                        </dt>
                        <dd class="font-mono">
                            {{ certificate.registration_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Fecha de emisión</dt>
                        <dd>{{ formatDate(certificate.issued_at) }}</dd>
                    </div>
                </div>
                <div v-if="certificate.revoked_at">
                    <dt class="text-muted-foreground">Fecha de anulación</dt>
                    <dd>{{ formatDate(certificate.revoked_at) }}</dd>
                </div>
            </dl>

            <p class="text-center text-xs text-muted-foreground">
                Verificación pública de certificados · Horus Group SRL
            </p>
        </div>
    </main>
</template>

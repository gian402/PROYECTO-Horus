<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, LogOut } from '@lucide/vue';
import EmptyState from '@/components/crm/EmptyState.vue';
import StatusBadge from '@/components/crm/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { logout } from '@/routes/portal';
import { download } from '@/routes/portal/certificates';
import type { PortalEnrollment, PortalStudent } from '@/types';

defineProps<{
    student: PortalStudent;
    enrollments: PortalEnrollment[];
}>();
</script>

<template>
    <Head title="Mi matrícula">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <main
        class="min-h-svh bg-linear-to-br from-[oklch(0.5_0.2_263)] via-[oklch(0.62_0.17_252)] to-[oklch(0.82_0.1_235)] p-4 sm:p-8 dark:from-[oklch(0.17_0.05_265)] dark:via-[oklch(0.22_0.07_265)] dark:to-[oklch(0.3_0.09_262)]"
    >
        <div class="mx-auto flex max-w-3xl flex-col gap-5">
            <div
                class="flex items-center justify-between rounded-[2rem] bg-card p-5 shadow-2xl shadow-black/20 sm:p-6"
            >
                <div class="flex items-center gap-3">
                    <img
                        src="/images/horus-logo.png"
                        alt="Horus Group"
                        class="h-11 w-auto"
                    />
                    <div class="leading-tight">
                        <p class="text-base font-bold text-primary sm:text-lg">
                            {{ student.full_name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            DNI {{ student.dni }}
                        </p>
                    </div>
                </div>

                <Link :href="logout()" as="button">
                    <Button variant="ghost" size="sm">
                        <LogOut /> Salir
                    </Button>
                </Link>
            </div>

            <div
                class="flex flex-col gap-3 rounded-[2rem] bg-card p-5 shadow-2xl shadow-black/20 sm:p-6"
            >
                <h2 class="text-base font-semibold">Mis matrículas</h2>

                <EmptyState
                    v-if="enrollments.length === 0"
                    title="Sin matrículas registradas"
                    description="No encontramos cursos a tu nombre todavía."
                />

                <div
                    v-for="e in enrollments"
                    :key="e.id"
                    class="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <div class="font-semibold">
                            {{ e.edition.course.name }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ e.edition.label }}
                            <template v-if="e.edition.teacher">
                                · {{ e.edition.teacher.full_name }}
                            </template>
                        </div>
                        <div
                            class="font-mono text-xs text-muted-foreground"
                        >
                            {{ e.registration_code }}
                        </div>
                    </div>

                    <div class="flex items-center gap-3 sm:flex-col sm:items-end">
                        <div class="flex items-center gap-2">
                            <StatusBadge :status="e.status" :role="e.role" />
                            <span
                                v-if="e.grade"
                                class="text-sm tabular-nums text-muted-foreground"
                            >
                                Nota {{ e.grade }}
                            </span>
                        </div>

                        <Button
                            v-if="e.certificate"
                            size="sm"
                            variant="outline"
                            as-child
                        >
                            <a
                                :href="
                                    download.url({
                                        certificate: e.certificate.id,
                                    })
                                "
                            >
                                <Download />
                                Certificado
                                ({{ formatDate(e.certificate.issued_at) }})
                            </a>
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>

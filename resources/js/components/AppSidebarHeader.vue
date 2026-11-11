<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, Moon, Search, Sun } from '@lucide/vue';
import { computed, ref } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useInitials } from '@/composables/useInitials';
import { usePermissions } from '@/composables/usePermissions';
import { index as studentsIndex } from '@/routes/students';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const { can } = usePermissions();
const { getInitials } = useInitials();
const { resolvedAppearance, updateAppearance } = useAppearance();

const user = computed(() => page.props.auth.user);
const role = computed(() => page.props.auth.roles?.[0] ?? null);
const canSearch = computed(
    () => can('students.view') || can('students.manage'),
);
const isDark = computed(() => resolvedAppearance.value === 'dark');

// Buscador de participantes: reutiliza el filtro `search` del listado.
const query = ref('');

function submitSearch(): void {
    const term = query.value.trim();

    if (term === '') {
        return;
    }

    router.get(studentsIndex().url, { search: term });
}
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b bg-card px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:px-6"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <Breadcrumbs
                v-if="breadcrumbs && breadcrumbs.length > 0"
                :breadcrumbs="breadcrumbs"
            />
        </div>

        <form
            v-if="canSearch"
            class="relative mx-auto hidden w-full max-w-sm md:block"
            role="search"
            @submit.prevent="submitSearch"
        >
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <input
                v-model="query"
                type="search"
                name="search"
                autocomplete="off"
                placeholder="Buscar participante por DNI o nombre"
                aria-label="Buscar participante por DNI o nombre"
                class="h-10 w-full rounded-full border border-transparent bg-muted pr-4 pl-9 text-sm outline-hidden transition-colors placeholder:text-muted-foreground focus:border-ring focus:bg-card"
            />
        </form>
        <div v-else class="mx-auto" />

        <div class="ml-auto flex items-center gap-1 md:ml-0">
            <Button
                variant="ghost"
                size="icon"
                class="rounded-full"
                :aria-label="
                    isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'
                "
                @click="updateAppearance(isDark ? 'light' : 'dark')"
            >
                <Sun v-if="isDark" class="size-5" />
                <Moon v-else class="size-5" />
            </Button>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex items-center gap-2.5 rounded-full py-1 pr-2 pl-1 transition-colors outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
                        data-test="sidebar-menu-button"
                    >
                        <Avatar class="size-9">
                            <AvatarImage
                                v-if="user.avatar"
                                :src="user.avatar"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="bg-primary text-sm font-semibold text-primary-foreground"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <span class="hidden text-left leading-tight lg:grid">
                            <span class="text-sm font-semibold">{{
                                user.name
                            }}</span>
                            <span
                                v-if="role"
                                class="text-xs text-muted-foreground"
                                >{{ role }}</span
                            >
                        </span>
                        <ChevronDown
                            class="hidden size-4 text-muted-foreground lg:block"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="min-w-56 rounded-lg">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>

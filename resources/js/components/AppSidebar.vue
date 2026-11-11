<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    CalendarRange,
    FileUp,
    GraduationCap,
    LayoutGrid,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { dashboard } from '@/routes';
import { index as coursesIndex } from '@/routes/courses';
import { index as editionsIndex } from '@/routes/editions';
import { index as importsIndex } from '@/routes/imports';
import { index as reportsIndex } from '@/routes/reports';
import { index as studentsIndex } from '@/routes/students';
import { index as teachersIndex } from '@/routes/teachers';
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';

const { canAny } = usePermissions();

const allItems: NavItem[] = [
    { title: 'Panel', href: dashboard(), icon: LayoutGrid },
    {
        title: 'Participantes',
        href: studentsIndex(),
        icon: Users,
        permissions: ['students.view', 'students.manage'],
    },
    {
        title: 'Ediciones',
        href: editionsIndex(),
        icon: CalendarRange,
        permissions: ['editions.view', 'editions.manage'],
    },
    {
        title: 'Cursos',
        href: coursesIndex(),
        icon: BookOpen,
        permissions: ['courses.manage'],
    },
    {
        title: 'Ponentes',
        href: teachersIndex(),
        icon: GraduationCap,
        permissions: ['teachers.manage'],
    },
    {
        title: 'Reportes',
        href: reportsIndex(),
        icon: BarChart3,
        permissions: ['reports.view'],
    },
    {
        title: 'Importar',
        href: importsIndex(),
        icon: FileUp,
        permissions: ['imports.run'],
    },
    {
        title: 'Usuarios',
        href: usersIndex(),
        icon: ShieldCheck,
        permissions: ['users.manage'],
    },
];

const mainNavItems = computed(() =>
    allItems.filter(
        (item) => !item.permissions || canAny(item.permissions),
    ),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

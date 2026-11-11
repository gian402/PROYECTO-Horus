<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    BookOpen,
    CalendarRange,
    FileUp,
    GraduationCap,
    LayoutGrid,
    Library,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
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
import type { NavGroup, NavItem } from '@/types';

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
        // Submenú: Cursos y Ponentes se tocan poco y se agrupan.
        title: 'Catálogo',
        href: coursesIndex(),
        icon: Library,
        permissions: ['courses.manage', 'teachers.manage'],
        children: [
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
        ],
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

// Secciones del menú; las que quedan vacías para el rol del usuario no se muestran.
const sections: { label: string; titles: string[] }[] = [
    { label: 'Principal', titles: ['Panel'] },
    {
        label: 'Gestión académica',
        titles: ['Participantes', 'Ediciones', 'Catálogo'],
    },
    { label: 'Análisis', titles: ['Reportes', 'Importar'] },
    { label: 'Administración', titles: ['Usuarios'] },
];

const navGroups = computed<NavGroup[]>(() =>
    sections
        .map((section) => ({
            label: section.label,
            items: allItems
                .filter(
                    (item) =>
                        section.titles.includes(item.title) &&
                        (!item.permissions || canAny(item.permissions)),
                )
                .map((item) => ({
                    ...item,
                    // Solo los submenús que el rol puede usar.
                    children: item.children?.filter(
                        (child) =>
                            !child.permissions || canAny(child.permissions),
                    ),
                })),
        }))
        .filter((group) => group.items.length > 0),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar">
        <SidebarHeader class="px-3 pt-4 pb-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="hover:bg-sidebar-accent/60"
                    >
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="navGroups" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>

import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Se muestra si el usuario tiene alguno de estos permisos. */
    permissions?: string[];
    /** Submenú desplegable (p. ej. «Catálogo» con Cursos y Ponentes). */
    children?: NavItem[];
};

/** Sección del menú lateral con su título (p. ej. «Gestión académica»). */
export type NavGroup = {
    label: string;
    items: NavItem[];
};

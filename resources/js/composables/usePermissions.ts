import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Permisos del usuario autenticado, compartidos por HandleInertiaRequests.
 * Solo sirven para mostrar u ocultar elementos: el backend vuelve a autorizar todo.
 */
export function usePermissions() {
    const page = usePage();
    const permissions = computed(() => page.props.auth.permissions ?? []);

    const can = (permission: string): boolean =>
        permissions.value.includes(permission);

    const canAny = (list: string[]): boolean => list.some((p) => can(p));

    return { permissions, can, canAny };
}

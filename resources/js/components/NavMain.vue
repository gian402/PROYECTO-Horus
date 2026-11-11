<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavGroup, NavItem } from '@/types';

defineProps<{
    groups: NavGroup[];
}>();

const { isCurrentOrParentUrl } = useCurrentUrl();
const { state, setOpen } = useSidebar();

// El elemento activo lleva una barra ámbar a la izquierda.
const activeBar =
    'relative text-sidebar-foreground/85 data-[active=true]:before:absolute data-[active=true]:before:top-1.5 data-[active=true]:before:bottom-1.5 data-[active=true]:before:-left-3 data-[active=true]:before:w-1 data-[active=true]:before:rounded-r-full data-[active=true]:before:bg-sidebar-primary';

const hasActiveChild = (item: NavItem): boolean =>
    (item.children ?? []).some((child) => isCurrentOrParentUrl(child.href));

// Con el menú contraído a iconos no caben los submenús: un clic lo expande.
function expandIfCollapsed(): void {
    if (state.value === 'collapsed') {
        setOpen(true);
    }
}
</script>

<template>
    <SidebarGroup v-for="group in groups" :key="group.label" class="px-3 py-1">
        <SidebarGroupLabel
            class="text-[11px] font-semibold tracking-wider text-sidebar-foreground/50 uppercase"
        >
            {{ group.label }}
        </SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in group.items" :key="item.title">
                <!-- Elemento con submenú desplegable -->
                <Collapsible
                    v-if="item.children?.length"
                    as-child
                    :default-open="hasActiveChild(item)"
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :tooltip="item.title"
                                :is-active="hasActiveChild(item)"
                                :class="activeBar"
                                @click="expandIfCollapsed"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub
                                class="mx-0 ml-5 border-sidebar-border px-2"
                            >
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="
                                            isCurrentOrParentUrl(child.href)
                                        "
                                        class="h-8 text-sidebar-foreground/80 hover:text-white data-[active=true]:text-white"
                                    >
                                        <Link :href="child.href">
                                            <component
                                                :is="child.icon"
                                                v-if="child.icon"
                                            />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>

                <!-- Elemento simple -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentOrParentUrl(item.href)"
                        :tooltip="item.title"
                        :class="activeBar"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>

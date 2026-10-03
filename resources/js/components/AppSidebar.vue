<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Images, LayoutGrid, UserRoundPen } from '@lucide/vue';
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
import { dashboard } from '@/routes';
import { index as portfolioIndex } from '@/routes/portfolio';
import { wizard } from '@/routes/provider';
import type { NavItem } from '@/types';

const page = usePage();

// Meniu priklauso nuo rolės: teikėjas dar mato profilio vedlį ir atliktus darbus
const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Mano paskyra',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    // --- Etapas 3: teikėjo profilis ---
    if (page.props.auth.user.role === 'provider') {
        items.push({
            title: 'Teikėjo profilis',
            href: wizard(),
            icon: UserRoundPen,
        });

        if (page.props.auth.user.provider_profile) {
            items.push({
                title: 'Atlikti darbai',
                href: portfolioIndex(),
                icon: Images,
            });
        }
    }

    return items;
});
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

<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Coins,
    House,
    Images,
    LayoutGrid,
    Receipt,
    UserRoundPen,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMarketplace from '@/components/marketplace/NavMarketplace.vue';
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
import { dashboard, home } from '@/routes';
import { index as creditsIndex } from '@/routes/credits';
import { index as paymentsIndex } from '@/routes/payments';
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

            // --- Etapas 7: kreditai ir mokėjimai ---
            items.push(
                { title: 'Kreditai', href: creditsIndex(), icon: Coins },
                { title: 'Mokėjimai', href: paymentsIndex(), icon: Receipt },
            );
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
            <!-- Etapas 5: užklausos, pasiūlymai, pranešimai (pagal rolę) -->
            <NavMarketplace />
        </SidebarContent>

        <SidebarFooter>
            <!-- Etapas 10: grįžimas į viešą svetainę (katalogą, teikėjų profilius) -->
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton as-child tooltip="Grįžti į svetainę">
                        <Link :href="home()">
                            <House />
                            <span>Grįžti į svetainę</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

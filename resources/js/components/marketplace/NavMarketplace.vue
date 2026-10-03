<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ClipboardList,
    Inbox,
    MessagesSquare,
    Plus,
    Send,
    Star,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { index as conversations } from '@/routes/conversations';
import { index as notifications } from '@/routes/notifications';
import { index as myOffers } from '@/routes/offers';
import { index as providerReviews } from '@/routes/provider-reviews';
import { index as feed } from '@/routes/provider-feed';
import { create, index as myRequests } from '@/routes/service-requests';
import type { NavItem } from '@/types';

// Etapas 5: šoninio meniu punktai pagal rolę (klientas / teikėjas)
const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const items = computed<NavItem[]>(() => {
    switch (page.props.auth.user.role) {
        case 'client':
            return [
                {
                    title: 'Mano užklausos',
                    href: myRequests(),
                    icon: ClipboardList,
                },
                { title: 'Nauja užklausa', href: create(), icon: Plus },
                // --- Etapas 6 ---
                {
                    title: 'Žinutės',
                    href: conversations(),
                    icon: MessagesSquare,
                },
                { title: 'Pranešimai', href: notifications(), icon: Bell },
            ];
        case 'provider':
            return [
                { title: 'Užklausų srautas', href: feed(), icon: Inbox },
                { title: 'Mano pasiūlymai', href: myOffers(), icon: Send },
                // --- Etapas 6 ---
                {
                    title: 'Žinutės',
                    href: conversations(),
                    icon: MessagesSquare,
                },
                { title: 'Atsiliepimai', href: providerReviews(), icon: Star },
                { title: 'Pranešimai', href: notifications(), icon: Bell },
            ];
        default:
            return [{ title: 'Pranešimai', href: notifications(), icon: Bell }];
    }
});

const unread = computed(() => page.props.notifications?.unread_count ?? 0);
// Etapas 6: neperskaitytos žinutės (bendras prop'as „inbox")
const unreadMessages = computed(() => page.props.inbox?.unread_count ?? 0);

function badgeFor(item: NavItem): number {
    switch (item.title) {
        case 'Pranešimai':
            return unread.value;
        case 'Žinutės':
            return unreadMessages.value;
        default:
            return 0;
    }
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Užklausos</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuBadge v-if="badgeFor(item) > 0">
                    {{ badgeFor(item) }}
                </SidebarMenuBadge>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

<script setup lang="ts">
import { Link, router, useHttp, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, ref } from 'vue';
import NotificationIcon from '@/components/marketplace/NotificationIcon.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { timeAgo } from '@/lib/marketplace';
import { index, latest, readAll } from '@/routes/notifications';
import type { AppNotification } from '@/types';

type LatestResponse = {
    notifications: AppNotification[];
    unread_count: number;
};

/*
 * Varpelis: neperskaitytų skaičius ateina su kiekvienu puslapiu (bendras Inertia prop'as „notifications"),
 * o patys pranešimai užkraunami tik atidarius sąrašą – useHttp (JSON užklausa be puslapio perkrovimo).
 * https://inertiajs.com/shared-data · https://inertiajs.com/http-requests
 */
const page = usePage();
const unread = computed(() => page.props.notifications?.unread_count ?? 0);

const http = useHttp<Record<string, never>, LatestResponse>({});
const items = ref<AppNotification[]>([]);
const loaded = ref(false);

async function load(): Promise<void> {
    const response = await http.get(latest.url());
    items.value = response.notifications;
    loaded.value = true;
}

function onOpenChange(open: boolean): void {
    if (open) {
        void load();
    }
}

function markAllRead(): void {
    router.post(
        readAll.url(),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                const now = new Date().toISOString();
                items.value = items.value.map((item) => ({
                    ...item,
                    read_at: item.read_at ?? now,
                }));
            },
        },
    );
}
</script>

<template>
    <DropdownMenu @update:open="onOpenChange">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                :aria-label="
                    unread > 0
                        ? `Pranešimai: ${unread} neperskaityti`
                        : 'Pranešimai'
                "
                data-test="notification-bell"
            >
                <Bell class="size-5" />
                <span
                    v-if="unread > 0"
                    class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold text-white"
                >
                    {{ unread > 9 ? '9+' : unread }}
                </span>
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80 p-0">
            <DropdownMenuLabel
                class="flex items-center justify-between px-3 py-2"
            >
                <span>Pranešimai</span>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="text-xs font-normal text-muted-foreground hover:text-foreground"
                    @click="markAllRead"
                >
                    Pažymėti visus perskaitytais
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator class="m-0" />

            <div v-if="!loaded" class="flex justify-center p-6">
                <Spinner />
            </div>
            <p
                v-else-if="items.length === 0"
                class="p-6 text-center text-sm text-muted-foreground"
            >
                Pranešimų nėra.
            </p>
            <ul v-else class="max-h-96 divide-y overflow-y-auto">
                <li v-for="item in items" :key="item.id">
                    <Link
                        :href="item.url"
                        class="flex gap-3 px-3 py-2.5 text-sm hover:bg-muted"
                        :class="{ 'bg-primary/5': !item.read_at }"
                    >
                        <NotificationIcon
                            :type="item.type"
                            class="mt-0.5 text-muted-foreground"
                        />
                        <span class="min-w-0 flex-1">
                            <span
                                class="line-clamp-2"
                                :class="{ 'font-medium': !item.read_at }"
                            >
                                {{ item.message }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ timeAgo(item.created_at) }}
                            </span>
                        </span>
                        <span
                            v-if="!item.read_at"
                            class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                            aria-label="Neperskaitytas"
                        />
                    </Link>
                </li>
            </ul>

            <DropdownMenuSeparator class="m-0" />
            <Link
                :href="index()"
                class="block px-3 py-2 text-center text-sm font-medium hover:bg-muted"
            >
                Visi pranešimai
            </Link>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

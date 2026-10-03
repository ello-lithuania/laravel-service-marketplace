<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import NotificationIcon from '@/components/marketplace/NotificationIcon.vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime, timeAgo } from '@/lib/marketplace';
import { edit as editSettings } from '@/routes/notification-settings';
import { index, read, readAll } from '@/routes/notifications';
import type { AppNotification, Paginated } from '@/types';

defineProps<{
    notifications: Paginated<AppNotification>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pranešimai', href: index() }],
    },
});

function markRead(id: string): void {
    router.post(read(id).url, {}, { preserveScroll: true });
}

function markAllRead(): void {
    router.post(readAll.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Pranešimai" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Pranešimai
                </h1>
                <p class="text-sm text-muted-foreground">
                    Kuriuos pranešimus gauti, galite pasirinkti
                    <Link
                        :href="editSettings()"
                        class="underline underline-offset-4"
                    >
                        nustatymuose </Link
                    >.
                </p>
            </div>
            <Button variant="outline" size="sm" @click="markAllRead">
                <Check class="size-4" /> Pažymėti visus perskaitytais
            </Button>
        </header>

        <p
            v-if="notifications.data.length === 0"
            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Pranešimų dar nėra.
        </p>

        <ul v-else class="divide-y rounded-xl border bg-card">
            <li
                v-for="item in notifications.data"
                :key="item.id"
                class="flex items-start gap-3 p-4"
                :class="{ 'bg-primary/5': !item.read_at }"
            >
                <NotificationIcon
                    :type="item.type"
                    class="mt-0.5 text-muted-foreground"
                />
                <div class="min-w-0 flex-1">
                    <Link
                        :href="item.url"
                        class="text-sm hover:underline"
                        :class="{ 'font-medium': !item.read_at }"
                    >
                        {{ item.message }}
                    </Link>
                    <p
                        class="text-xs text-muted-foreground"
                        :title="formatDateTime(item.created_at)"
                    >
                        {{ timeAgo(item.created_at) }}
                    </p>
                </div>
                <button
                    v-if="!item.read_at"
                    type="button"
                    class="shrink-0 text-xs text-muted-foreground hover:text-foreground"
                    @click="markRead(item.id)"
                >
                    Perskaityta
                </button>
            </li>
        </ul>

        <PaginationLinks :paginator="notifications" />
    </div>
</template>

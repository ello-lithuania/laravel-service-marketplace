<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { MessagesSquare } from '@lucide/vue';
import PaginationLinks from '@/components/marketplace/PaginationLinks.vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { formatDateTime, timeAgo } from '@/lib/marketplace';
import { index, show } from '@/routes/conversations';
import type { ConversationListItem, Paginated } from '@/types';

defineProps<{
    conversations: Paginated<ConversationListItem>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Žinutės', href: index() }],
    },
});

/*
 * Atnaujinimas „beveik realiu laiku" – polling: kas 30 s Inertia iš serverio paima tik pokalbių sąrašą ir
 * neperskaitytų skaičių (dalinis perkrovimas su „only"). Kai skirtukas fone, Inertia užklausas retina pati.
 * https://inertiajs.com/polling · sprendimas dėl Reverb – docs/drafts/etapas-6.md
 */
usePoll(30_000, { only: ['conversations', 'inbox'] });
</script>

<template>
    <Head title="Žinutės" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
        <header>
            <h1 class="text-2xl font-semibold tracking-tight">Žinutės</h1>
            <p class="text-sm text-muted-foreground">
                Pokalbiai su klientais ir teikėjais dėl pasiūlymų.
            </p>
        </header>

        <div
            v-if="conversations.data.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            <MessagesSquare class="size-8" />
            <p>
                Pokalbių dar nėra. Parašyti galite iš pasiūlymo puslapio
                (mygtukas „Rašyti žinutę").
            </p>
        </div>

        <ul v-else class="divide-y rounded-xl border bg-card">
            <li v-for="item in conversations.data" :key="item.id">
                <Link
                    :href="show(item.id)"
                    class="flex items-start gap-3 p-4 transition-colors hover:bg-muted/50"
                    :class="{ 'bg-primary/5': item.unread_count > 0 }"
                    data-test="conversation-row"
                >
                    <Avatar class="size-10 shrink-0">
                        <AvatarFallback
                            class="bg-primary/10 text-sm font-semibold text-primary"
                        >
                            {{ getInitials(item.counterpart.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <p
                                class="truncate"
                                :class="
                                    item.unread_count > 0
                                        ? 'font-semibold'
                                        : 'font-medium'
                                "
                            >
                                {{ item.counterpart.name }}
                            </p>
                            <time
                                v-if="item.last_message_at"
                                :datetime="item.last_message_at"
                                :title="formatDateTime(item.last_message_at)"
                                class="shrink-0 text-xs text-muted-foreground"
                            >
                                {{ timeAgo(item.last_message_at) }}
                            </time>
                        </div>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ item.title }}
                        </p>
                        <div
                            class="mt-1 flex items-center justify-between gap-3"
                        >
                            <p
                                v-if="item.last_message"
                                class="truncate text-sm"
                                :class="
                                    item.unread_count > 0
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                            >
                                <span v-if="item.last_message.is_mine"
                                    >Jūs:
                                </span>
                                {{ item.last_message.excerpt }}
                            </p>
                            <span
                                v-if="item.unread_count > 0"
                                class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-xs font-semibold text-primary-foreground"
                                :aria-label="`Neperskaitytos: ${item.unread_count}`"
                            >
                                {{ item.unread_count }}
                            </span>
                            <StatusBadge
                                v-else-if="item.offer_status"
                                :status="item.offer_status"
                                class="shrink-0"
                            />
                        </div>
                    </div>
                </Link>
            </li>
        </ul>

        <PaginationLinks :paginator="conversations" />
    </div>
</template>

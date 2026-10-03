<script setup lang="ts">
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Send } from '@lucide/vue';
import { nextTick, onMounted, useTemplateRef, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatMoney } from '@/lib/marketplace';
import { index } from '@/routes/conversations';
import { store } from '@/routes/messages';
import { show as showRequest } from '@/routes/service-requests';
import type { ChatMessage, ConversationDetail } from '@/types';

const props = defineProps<{
    conversation: ConversationDetail;
    messages: ChatMessage[];
    can: { send: boolean; reason: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Žinutės', href: index() }],
    },
});

const MAX_BODY = 5000;

/*
 * Polling: kas 10 s Inertia paima tik žinutes (ir ar dar galima rašyti). Serveris tuo pačiu pažymi pokalbį
 * perskaitytu, todėl kita pusė mato, kad žinutė perskaityta, o meniu ženklelis sumažėja.
 */
usePoll(10_000, { only: ['messages', 'can', 'inbox'] });

const list = useTemplateRef<HTMLElement>('list');

function scrollToBottom(): void {
    void nextTick(() => {
        if (list.value) {
            list.value.scrollTop = list.value.scrollHeight;
        }
    });
}

onMounted(scrollToBottom);

// Atėjus naujai žinutei – žemyn, bet tik jei vartotojas ir taip buvo apačioje (neskaito senų žinučių)
watch(
    () => props.messages.length,
    (length, previous) => {
        const el = list.value;
        const nearBottom =
            !el || el.scrollHeight - el.scrollTop - el.clientHeight < 160;
        const lastIsMine = props.messages[length - 1]?.is_mine ?? false;

        if (length > previous && (nearBottom || lastIsMine)) {
            scrollToBottom();
        }
    },
);

const form = useForm({ body: '' });

function send(): void {
    if (form.processing || form.body.trim() === '') {
        return;
    }

    form.post(store(props.conversation.id).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

// Ctrl/Cmd + Enter – išsiųsti (paprastas Enter – nauja eilutė, kad būtų patogu rašyti ilgesnį tekstą)
function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        send();
    }
}
</script>

<template>
    <Head :title="`Pokalbis: ${conversation.counterpart.name}`" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-4 p-4 md:p-6">
        <header
            class="flex flex-wrap items-start justify-between gap-3 rounded-xl border bg-card p-4"
        >
            <div class="min-w-0 space-y-1">
                <Link
                    :href="index()"
                    class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-3.5" /> Visi pokalbiai
                </Link>
                <h1 class="truncate text-lg font-semibold">
                    {{ conversation.counterpart.name }}
                </h1>
                <p
                    v-if="conversation.service_request"
                    class="text-sm text-muted-foreground"
                >
                    Užklausa:
                    <Link
                        :href="showRequest(conversation.service_request.slug)"
                        class="underline-offset-4 hover:underline"
                    >
                        {{ conversation.service_request.title }}
                    </Link>
                </p>
            </div>
            <div
                v-if="conversation.offer"
                class="flex flex-col items-end gap-1 text-sm"
            >
                <StatusBadge :status="conversation.offer.status" />
                <span class="text-muted-foreground">
                    {{
                        conversation.offer.price_cents !== null
                            ? formatMoney(conversation.offer.price_cents)
                            : 'Kaina po apžiūros'
                    }}
                    · {{ conversation.offer.price_type }}
                </span>
            </div>
        </header>

        <section
            ref="list"
            class="flex h-[55vh] min-h-72 flex-col gap-3 overflow-y-auto rounded-xl border bg-muted/20 p-4"
            aria-label="Žinutės"
            aria-live="polite"
        >
            <p
                v-if="messages.length === 0"
                class="m-auto text-center text-sm text-muted-foreground"
            >
                Žinučių dar nėra – parašykite pirmą.
            </p>

            <article
                v-for="message in messages"
                :key="message.id"
                class="flex max-w-[85%] flex-col gap-1"
                :class="message.is_mine ? 'items-end self-end' : 'items-start'"
                :data-test="message.is_mine ? 'message-mine' : 'message-theirs'"
            >
                <div
                    class="rounded-2xl px-3.5 py-2 text-sm"
                    :class="[
                        message.is_hidden || message.is_system
                            ? 'border border-dashed bg-background text-muted-foreground italic'
                            : message.is_mine
                              ? 'rounded-br-sm bg-primary text-primary-foreground'
                              : 'rounded-bl-sm border bg-background',
                    ]"
                >
                    <p v-if="message.is_hidden">
                        Žinutė paslėpta administratoriaus.
                    </p>
                    <p v-else class="break-words whitespace-pre-line">
                        {{ message.body }}
                    </p>
                </div>
                <p class="px-1 text-xs text-muted-foreground">
                    <span v-if="!message.is_mine"
                        >{{ message.sender_name }} ·
                    </span>
                    <time
                        v-if="message.created_at"
                        :datetime="message.created_at"
                        >{{ formatDateTime(message.created_at) }}</time
                    >
                </p>
            </article>
        </section>

        <form
            v-if="can.send"
            class="space-y-2 rounded-xl border bg-card p-3"
            @submit.prevent="send"
        >
            <label for="body" class="sr-only">Žinutė</label>
            <textarea
                id="body"
                v-model="form.body"
                rows="3"
                :maxlength="MAX_BODY"
                placeholder="Rašykite žinutę… (Ctrl + Enter – išsiųsti)"
                class="w-full resize-y rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                :aria-invalid="!!form.errors.body || undefined"
                data-test="message-body"
                @keydown="onKeydown"
            />
            <InputError :message="form.errors.body" />
            <div class="flex items-center justify-end gap-3">
                <Button
                    type="submit"
                    :disabled="form.processing || form.body.trim() === ''"
                    data-test="send-message"
                >
                    <Spinner v-if="form.processing" />
                    <Send v-else class="size-4" />
                    Siųsti
                </Button>
            </div>
        </form>

        <p
            v-else
            class="flex items-center justify-center gap-2 rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground"
        >
            <Lock class="size-4" />
            {{ can.reason }}
        </p>
    </div>
</template>

<script setup lang="ts">
import { Head, InfiniteScroll, Link, useForm, usePoll } from '@inertiajs/vue3';
import { ArrowLeft, Lock, Paperclip, Send, X } from '@lucide/vue';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import ReportDialog from '@/components/complaints/ReportDialog.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/marketplace/StatusBadge.vue';
import AttachmentList from '@/components/messages/AttachmentList.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime, formatMoney } from '@/lib/marketplace';
import { attachmentError, formatFileSize } from '@/lib/messages';
import { index } from '@/routes/conversations';
import { store } from '@/routes/messages';
import { show as showRequest } from '@/routes/service-requests';
import type { ChatMessage, ChatMessagePage, ConversationDetail } from '@/types';

const props = defineProps<{
    conversation: ConversationDetail;
    messages: ChatMessagePage;
    can: { send: boolean; reason: string | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Žinutės', href: index() }],
    },
});

const MAX_BODY = 5000;

// Pranešti galima tik dalyviui (ne administratoriui) ir tik apie kito žmogaus žinutę
const isParticipant = computed(
    () => props.conversation.counterpart.role !== 'both',
);

function canReport(message: ChatMessage): boolean {
    return !message.is_mine && !message.is_system && !message.is_hidden;
}

/*
 * Polling: kas 10 s Inertia paima tik žinutes (ir ar dar galima rašyti). Serveris tuo pačiu pažymi pokalbį
 * perskaitytu, todėl kita pusė mato, kad žinutė perskaityta, o meniu ženklelis sumažėja.
 * Atsakyme – naujausių žinučių puslapis. Jis prijungiamas prie jau įkeltų (merge su matchOn('data.id')),
 * todėl senesnės, slenkant aukštyn įkeltos žinutės nedingsta ir nesidubliuoja.
 */
const RELOAD_PROPS = ['messages', 'can', 'inbox'];

usePoll(10_000, { only: RELOAD_PROPS });

/*
 * messages.data – puslapiai tokia tvarka, kokia jie atėjo: naujausių puslapis, senesni, o polling'o naujienos –
 * gale. Todėl rodymui surikiuojam pagal id (id didėja kartu su laiku): seniausios viršuje, naujausios apačioje.
 */
const ordered = computed(() =>
    [...props.messages.data].sort((a, b) => a.id - b.id),
);

const list = useTemplateRef<HTMLElement>('list');

function scrollToBottom(): void {
    void nextTick(() => {
        if (list.value) {
            list.value.scrollTop = list.value.scrollHeight;
        }
    });
}

/*
 * Atėjus naujai žinutei – žemyn, bet tik jei vartotojas ir taip buvo apačioje (neskaito senų žinučių).
 * Stebim naujausios žinutės id, o ne kiekį: įkėlus senesnes žinutes kiekis irgi padidėja, bet tada
 * slinkti žemyn nereikia (pradinį nuslinkimą į apačią ir vietos išlaikymą atlieka InfiniteScroll).
 */
watch(
    () => ordered.value.at(-1)?.id ?? 0,
    (newest, previous) => {
        const el = list.value;
        const nearBottom =
            !el || el.scrollHeight - el.scrollTop - el.clientHeight < 160;
        const lastIsMine = ordered.value.at(-1)?.is_mine ?? false;

        if (newest > previous && (nearBottom || lastIsMine)) {
            scrollToBottom();
        }
    },
);

const MAX_ATTACHMENTS = 5;

const form = useForm({ body: '', attachments: [] as File[] });
const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const fileError = ref<string | null>(null);

const canSubmit = computed(
    () =>
        !form.processing &&
        (form.body.trim() !== '' || form.attachments.length > 0),
);

// Serverio klaidos apie konkretų failą ateina kaip „attachments.0", „attachments.1"…
const attachmentsError = computed(
    () =>
        fileError.value ??
        form.errors.attachments ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('attachments.'),
        )?.[1],
);

function addFiles(event: Event): void {
    fileError.value = null;

    for (const file of Array.from(
        (event.target as HTMLInputElement).files ?? [],
    )) {
        if (form.attachments.length >= MAX_ATTACHMENTS) {
            fileError.value = `Vienoje žinutėje – daugiausia ${MAX_ATTACHMENTS} priedai.`;
            break;
        }

        const error = attachmentError(file);

        if (error) {
            fileError.value = error;
            continue;
        }

        form.attachments.push(file);
    }

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function removeFile(index: number): void {
    form.attachments.splice(index, 1);
}

function send(): void {
    if (!canSubmit.value) {
        return;
    }

    // Su failais Inertia pati siunčia multipart/form-data (FormData).
    // only + preserveState: po nukreipimo atgal į pokalbį perkraunamos tik žinutės (merge, kaip polling'e),
    // todėl jau įkeltos senesnės žinutės lieka, o komponentas neperkuriamas.
    form.post(store(props.conversation.id).url, {
        only: RELOAD_PROPS,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            fileError.value = null;
        },
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
                v-if="ordered.length === 0"
                class="m-auto text-center text-sm text-muted-foreground"
            >
                Žinučių dar nėra – parašykite pirmą.
            </p>

            <!--
                Begalinis slinkimas atvirkščiai (reverse), kaip pokalbių programose: atidarius rodoma apačia,
                o priartėjus prie viršaus įkeliamas kitas (senesnis) puslapis. preserve-url – adresas nesikeičia.
            -->
            <InfiniteScroll
                v-else
                data="messages"
                reverse
                only-next
                preserve-url
                class="flex flex-col gap-3"
            >
                <template #next="{ loading, fetch, hasMore }">
                    <div
                        class="flex min-h-8 items-center justify-center pb-2 text-xs text-muted-foreground"
                    >
                        <span v-if="loading" class="flex items-center gap-2">
                            <Spinner /> Įkeliamos senesnės žinutės…
                        </span>
                        <!-- Mygtukas – jei automatinis įkėlimas nesuveikė (pvz. naršant klaviatūra) -->
                        <Button
                            v-else-if="hasMore"
                            type="button"
                            variant="ghost"
                            size="sm"
                            data-test="load-older"
                            @click="fetch"
                        >
                            Rodyti senesnes žinutes
                        </Button>
                        <span v-else>Pokalbio pradžia</span>
                    </div>
                </template>

                <article
                    v-for="message in ordered"
                    :key="message.id"
                    class="flex max-w-[85%] flex-col gap-1"
                    :class="
                        message.is_mine ? 'items-end self-end' : 'items-start'
                    "
                    :data-test="
                        message.is_mine ? 'message-mine' : 'message-theirs'
                    "
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
                        <p
                            v-else-if="message.body"
                            class="break-words whitespace-pre-line"
                        >
                            {{ message.body }}
                        </p>
                        <AttachmentList
                            v-if="message.attachments.length"
                            :files="message.attachments"
                            :mine="message.is_mine"
                            :class="{ 'mt-2': message.body }"
                        />
                    </div>
                    <div
                        class="flex items-center gap-1 px-1 text-xs text-muted-foreground"
                    >
                        <span v-if="!message.is_mine"
                            >{{ message.sender_name }} ·
                        </span>
                        <time
                            v-if="message.created_at"
                            :datetime="message.created_at"
                            >{{ formatDateTime(message.created_at) }}</time
                        >
                        <!-- Etapas 6: pranešti apie kito dalyvio žinutę -->
                        <ReportDialog
                            v-if="isParticipant && canReport(message)"
                            type="message"
                            :id="message.id"
                            compact
                        />
                    </div>
                </article>
            </InfiniteScroll>
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

            <!-- Pasirinkti, bet dar neišsiųsti priedai -->
            <ul v-if="form.attachments.length" class="flex flex-wrap gap-2">
                <li
                    v-for="(file, i) in form.attachments"
                    :key="`${file.name}-${i}`"
                    class="inline-flex max-w-full items-center gap-2 rounded-full border bg-muted/40 py-1 pr-1 pl-3 text-xs"
                >
                    <span class="truncate">{{ file.name }}</span>
                    <span class="text-muted-foreground">
                        {{ formatFileSize(file.size) }}
                    </span>
                    <button
                        type="button"
                        class="rounded-full p-1 hover:bg-muted"
                        :aria-label="`Pašalinti ${file.name}`"
                        @click="removeFile(i)"
                    >
                        <X class="size-3" />
                    </button>
                </li>
            </ul>
            <InputError :message="attachmentsError" />

            <div class="flex items-center justify-between gap-3">
                <div>
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="sr-only"
                        data-test="attachments-input"
                        @change="addFiles"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="form.attachments.length >= MAX_ATTACHMENTS"
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-4" /> Pridėti failą
                    </Button>
                    <span
                        class="hidden text-xs text-muted-foreground sm:inline"
                    >
                        Nuotraukos iki 5 MB, PDF iki 10 MB
                    </span>
                </div>
                <Button
                    type="submit"
                    :disabled="!canSubmit"
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

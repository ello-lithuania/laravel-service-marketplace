<script setup lang="ts">
import { onKeyStroke } from '@vueuse/core';
import { ChevronLeft, ChevronRight, ImageIcon, Images } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatMonth } from '@/lib/format';
import type { PortfolioItem } from '@/types';

// Atlikti darbai: kortelės su pirma nuotrauka, paspaudus – peržiūra visame ekrane (lightbox) su visomis darbo
// nuotraukomis. Dialog (reka-ui) pats pasirūpina fokusu, Esc ir ekrano skaitytuvais; rodyklės ← → – nuotraukos.
const props = defineProps<{ items: PortfolioItem[] }>();

const openItemIndex = ref<number | null>(null);
const imageIndex = ref(0);

const openItem = computed(() =>
    openItemIndex.value === null ? null : props.items[openItemIndex.value],
);

const currentImage = computed(
    () => openItem.value?.images[imageIndex.value] ?? null,
);

function open(index: number): void {
    openItemIndex.value = index;
    imageIndex.value = 0;
}

function step(delta: number): void {
    const count = openItem.value?.images.length ?? 0;

    if (count > 1) {
        imageIndex.value = (imageIndex.value + delta + count) % count;
    }
}

onKeyStroke('ArrowRight', () => openItem.value && step(1));
onKeyStroke('ArrowLeft', () => openItem.value && step(-1));

function meta(item: PortfolioItem): string {
    return [
        item.category,
        item.city,
        item.completed_date ? formatMonth(item.completed_date) : null,
    ]
        .filter(Boolean)
        .join(' · ');
}
</script>

<template>
    <ul class="grid gap-4 sm:grid-cols-2">
        <li
            v-for="(item, index) in items"
            :key="item.id"
            :class="index === 0 && items.length > 2 ? 'sm:col-span-2' : ''"
        >
            <article
                class="group overflow-hidden rounded-2xl border bg-card shadow-soft transition-shadow hover:shadow-lift"
            >
                <button
                    v-if="item.images.length"
                    type="button"
                    class="relative block w-full overflow-hidden bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :class="
                        index === 0 && items.length > 2
                            ? 'aspect-[16/8]'
                            : 'aspect-[4/3]'
                    "
                    :aria-label="`Peržiūrėti nuotraukas: ${item.title}`"
                    @click="open(index)"
                >
                    <img
                        :src="
                            index === 0 && items.length > 2
                                ? item.images[0].url
                                : item.images[0].thumb_url
                        "
                        :alt="item.title"
                        width="480"
                        height="360"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover transition-transform duration-700 group-hover:scale-[1.03]"
                    />
                    <span
                        v-if="item.images.length > 1"
                        class="absolute right-3 bottom-3 inline-flex items-center gap-1.5 rounded-full bg-black/60 px-2.5 py-1 text-xs font-medium text-white backdrop-blur-sm"
                    >
                        <Images class="size-3.5" aria-hidden="true" />
                        {{ item.images.length }}
                    </span>
                </button>
                <div
                    v-else
                    class="flex aspect-[4/3] items-center justify-center bg-muted"
                >
                    <ImageIcon
                        class="size-8 text-muted-foreground/40"
                        aria-hidden="true"
                    />
                </div>
                <div class="p-4">
                    <h3 class="font-semibold">{{ item.title }}</h3>
                    <p
                        v-if="meta(item)"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ meta(item) }}
                    </p>
                    <p
                        v-if="item.description"
                        class="mt-2 line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ item.description }}
                    </p>
                </div>
            </article>
        </li>
    </ul>

    <Dialog
        :open="openItem !== null"
        @update:open="(value) => !value && (openItemIndex = null)"
    >
        <DialogContent
            class="max-w-[calc(100%-1.5rem)] gap-0 overflow-hidden border-0 bg-black p-0 text-white sm:max-w-5xl [&>[data-slot=dialog-close]]:z-10 [&>[data-slot=dialog-close]]:rounded-full [&>[data-slot=dialog-close]]:bg-black/60 [&>[data-slot=dialog-close]]:p-2 [&>[data-slot=dialog-close]]:text-white [&>[data-slot=dialog-close]]:opacity-100"
        >
            <template v-if="openItem && currentImage">
                <div
                    class="relative flex max-h-[75vh] items-center justify-center bg-black"
                >
                    <img
                        :key="currentImage.url"
                        :src="currentImage.url"
                        :alt="`${openItem.title} – ${imageIndex + 1} nuotrauka iš ${openItem.images.length}`"
                        class="max-h-[75vh] w-auto object-contain"
                    />
                    <template v-if="openItem.images.length > 1">
                        <button
                            type="button"
                            class="absolute left-3 flex size-11 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm transition-colors hover:bg-black/75 focus-visible:ring-[3px] focus-visible:ring-white/60 focus-visible:outline-none"
                            aria-label="Ankstesnė nuotrauka"
                            @click="step(-1)"
                        >
                            <ChevronLeft class="size-6" />
                        </button>
                        <button
                            type="button"
                            class="absolute right-3 flex size-11 items-center justify-center rounded-full bg-black/55 text-white backdrop-blur-sm transition-colors hover:bg-black/75 focus-visible:ring-[3px] focus-visible:ring-white/60 focus-visible:outline-none"
                            aria-label="Kita nuotrauka"
                            @click="step(1)"
                        >
                            <ChevronRight class="size-6" />
                        </button>
                    </template>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-3 bg-neutral-950 px-5 py-4"
                >
                    <div class="min-w-0">
                        <DialogTitle
                            class="text-base font-semibold text-white"
                            >{{ openItem.title }}</DialogTitle
                        >
                        <DialogDescription class="text-sm text-white/65">
                            {{ meta(openItem) || 'Atliktas darbas' }}
                        </DialogDescription>
                    </div>
                    <p
                        v-if="openItem.images.length > 1"
                        class="text-sm text-white/65 numeric"
                        aria-live="polite"
                    >
                        {{ imageIndex + 1 }} / {{ openItem.images.length }}
                    </p>
                </div>
                <!-- Miniatiūros -->
                <div
                    v-if="openItem.images.length > 1"
                    class="flex gap-2 overflow-x-auto bg-neutral-950 px-5 pb-4"
                >
                    <button
                        v-for="(image, index) in openItem.images"
                        :key="image.url"
                        type="button"
                        class="size-16 shrink-0 overflow-hidden rounded-lg ring-2 transition"
                        :class="
                            index === imageIndex
                                ? 'ring-cta'
                                : 'opacity-60 ring-transparent hover:opacity-100'
                        "
                        :aria-label="`${index + 1} nuotrauka`"
                        :aria-current="
                            index === imageIndex ? 'true' : undefined
                        "
                        @click="imageIndex = index"
                    >
                        <img
                            :src="image.thumb_url"
                            alt=""
                            width="64"
                            height="64"
                            class="size-full object-cover"
                        />
                    </button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>

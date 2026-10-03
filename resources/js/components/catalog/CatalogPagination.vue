<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

// Laravel paginator'iaus nuorodos (meta.links): [« Ankstesnis, 1, 2, …, 9, Kitas »].
// Pirmą ir paskutinį elementą keičiam savo mygtukais su ikonomis, vidurinius rodom kaip numerius.
// only – Inertia dalinis perkrovimas: iš serverio paimami tik nurodyti props (pvz. tik atsiliepimai).
const props = withDefaults(
    defineProps<{
        paginated: Paginated<unknown>;
        only?: string[];
        preserveScroll?: boolean;
    }>(),
    { only: () => [], preserveScroll: false },
);

const pages = computed(() => props.paginated.meta.links.slice(1, -1));
</script>

<template>
    <nav
        v-if="paginated.meta.last_page > 1"
        class="flex flex-wrap items-center justify-center gap-1"
        aria-label="Puslapiai"
    >
        <Button
            v-if="paginated.links.prev"
            variant="outline"
            size="sm"
            as-child
        >
            <Link
                :href="paginated.links.prev"
                rel="prev"
                :only="only"
                :preserve-scroll="preserveScroll"
            >
                <ChevronLeft aria-hidden="true" />
                <span class="sr-only sm:not-sr-only">Ankstesnis</span>
            </Link>
        </Button>

        <template v-for="(page, index) in pages" :key="index">
            <span
                v-if="page.url === null"
                class="px-2 text-sm text-muted-foreground"
                >{{ page.label }}</span
            >
            <Button
                v-else
                :variant="page.active ? 'default' : 'ghost'"
                size="sm"
                class="min-w-9"
                as-child
            >
                <Link
                    :href="page.url"
                    :aria-current="page.active ? 'page' : undefined"
                    :only="only"
                    :preserve-scroll="preserveScroll"
                    >{{ page.label }}</Link
                >
            </Button>
        </template>

        <Button
            v-if="paginated.links.next"
            variant="outline"
            size="sm"
            as-child
        >
            <Link
                :href="paginated.links.next"
                rel="next"
                :only="only"
                :preserve-scroll="preserveScroll"
            >
                <span class="sr-only sm:not-sr-only">Kitas</span>
                <ChevronRight aria-hidden="true" />
            </Link>
        </Button>
    </nav>
</template>

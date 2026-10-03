<script setup lang="ts">
import { ChevronLeft, ChevronRight, Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { CategoryNode } from '@/types';

/**
 * 3 lygių kategorijų medžio pasirinkimas su paieška. Pasirinkti galima tik 3 lygio paslaugą (užklausos
 * visada priskiriamos lapui – docs/DB_SCHEMA.md 2.2). Paieška ignoruoja lietuviškas raides: „plyteles" randa „Plytelių".
 */
type Leaf = { id: number; name: string; path: string; search: string };

const props = defineProps<{
    categories: CategoryNode[];
    invalid?: boolean;
}>();

const model = defineModel<number | null>({ default: null });

const query = ref('');
const openRootId = ref<number | null>(null);

// „Plytelių" → „plyteliu": NFD išskaido raidę ir diakritiką, o diakritiką išmetam
function normalize(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

// Visi lapai su keliu „Sritis › Kategorija" – paieškai ir pasirinkimo rodymui
const leaves = computed<Leaf[]>(() =>
    props.categories.flatMap((root) =>
        root.children.flatMap((group) =>
            group.children.map((leaf) => ({
                id: leaf.id,
                name: leaf.name,
                path: `${root.name} › ${group.name}`,
                search: normalize(`${leaf.name} ${group.name} ${root.name}`),
            })),
        ),
    ),
);

const selected = computed(
    () => leaves.value.find((leaf) => leaf.id === model.value) ?? null,
);

const results = computed(() => {
    const words = normalize(query.value).split(/\s+/).filter(Boolean);

    if (words.length === 0 || query.value.trim().length < 2) {
        return [];
    }

    return leaves.value
        .filter((leaf) => words.every((word) => leaf.search.includes(word)))
        .slice(0, 40);
});

const openRoot = computed(
    () => props.categories.find((root) => root.id === openRootId.value) ?? null,
);

function choose(id: number): void {
    model.value = id;
    query.value = '';
}

function reset(): void {
    model.value = null;
    openRootId.value = null;
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="selected"
            class="flex items-center justify-between gap-3 rounded-lg border border-primary/40 bg-primary/5 p-3"
        >
            <div>
                <p class="text-xs text-muted-foreground">{{ selected.path }}</p>
                <p class="font-medium">{{ selected.name }}</p>
            </div>
            <Button type="button" variant="ghost" size="sm" @click="reset">
                <X class="size-4" /> Keisti
            </Button>
        </div>

        <template v-else>
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    class="pl-9"
                    placeholder="Ieškokite paslaugos, pvz. „plytelių klijavimas“"
                    :aria-invalid="invalid || undefined"
                    autocomplete="off"
                    data-test="category-search"
                />
            </div>

            <!-- Paieškos rezultatai -->
            <ul
                v-if="query.trim().length >= 2"
                class="max-h-80 divide-y overflow-y-auto rounded-lg border"
            >
                <li v-for="leaf in results" :key="leaf.id">
                    <button
                        type="button"
                        class="w-full px-3 py-2 text-left hover:bg-muted"
                        @click="choose(leaf.id)"
                    >
                        <span class="block font-medium">{{ leaf.name }}</span>
                        <span class="block text-xs text-muted-foreground">
                            {{ leaf.path }}
                        </span>
                    </button>
                </li>
                <li
                    v-if="results.length === 0"
                    class="px-3 py-4 text-sm text-muted-foreground"
                >
                    Nieko nerasta. Pabandykite kitą žodį arba pasirinkite iš
                    sąrašo.
                </li>
            </ul>

            <!-- Medis: sritis → kategorijos → paslaugos -->
            <div v-else-if="openRoot" class="space-y-3 rounded-lg border p-3">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    @click="openRootId = null"
                >
                    <ChevronLeft class="size-4" /> Visos sritys
                </Button>
                <p class="font-semibold">{{ openRoot.name }}</p>
                <div
                    v-for="group in openRoot.children"
                    :key="group.id"
                    class="space-y-1.5"
                >
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ group.name }}
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="leaf in group.children"
                            :key="leaf.id"
                            type="button"
                            class="rounded-full border px-3 py-1 text-sm hover:border-primary hover:bg-primary/5"
                            @click="choose(leaf.id)"
                        >
                            {{ leaf.name }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="grid gap-2 sm:grid-cols-2">
                <button
                    v-for="root in categories"
                    :key="root.id"
                    type="button"
                    class="flex items-center justify-between rounded-lg border px-3 py-2.5 text-left hover:border-primary hover:bg-primary/5"
                    @click="openRootId = root.id"
                >
                    <span class="font-medium">{{ root.name }}</span>
                    <ChevronRight class="size-4 text-muted-foreground" />
                </button>
            </div>
        </template>
    </div>
</template>

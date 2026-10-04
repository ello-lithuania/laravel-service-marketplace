<script setup lang="ts">
import { ChevronRight, Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { CategoryNode } from '@/types';

// 3 lygių kategorijų medis su varnelėmis (docs/DB_SCHEMA.md 2.2):
// pažymėtas tėvas reiškia „visi jo vaikai", todėl vaikai tada rodomi pažymėti ir neaktyvūs,
// o į serverį siunčiamas tik pats tėvas.
const props = defineProps<{
    tree: CategoryNode[];
    max: number;
    // Etapas 9c: jau išsaugotos kategorijos. Teikėjas, kurio jų daugiau nei leidžia planas, gali jas palikti
    // ar pašalinti, bet ne pridėti naujų (ta pati taisyklė kaip SyncProviderCategories serveryje)
    initial?: number[];
}>();

const selected = defineModel<number[]>({ required: true });

// Pagalbinės lentelės: kas kieno tėvas, koks pavadinimas (medis nekinta, todėl skaičiuojam vieną kartą)
const parentOf = new Map<number, number | null>();
const nameOf = new Map<number, string>();

function indexTree(nodes: CategoryNode[], parent: number | null): void {
    for (const node of nodes) {
        parentOf.set(node.id, parent);
        nameOf.set(node.id, node.name);
        indexTree(node.children, node.id);
    }
}

indexTree(props.tree, null);

const selectedSet = computed(() => new Set(selected.value));

function descendantIds(node: CategoryNode): number[] {
    return node.children.flatMap((child) => [
        child.id,
        ...descendantIds(child),
    ]);
}

function isCovered(id: number): boolean {
    for (
        let parent = parentOf.get(id) ?? null;
        parent !== null;
        parent = parentOf.get(parent) ?? null
    ) {
        if (selectedSet.value.has(parent)) {
            return true;
        }
    }

    return false;
}

function state(node: CategoryNode): boolean | 'indeterminate' {
    if (selectedSet.value.has(node.id) || isCovered(node.id)) {
        return true;
    }

    return descendantIds(node).some((id) => selectedSet.value.has(id))
        ? 'indeterminate'
        : false;
}

// Pasirinkimas pažymėjus mazgą: tėvas jau apima vaikus – atskirai jų laikyti nereikia
function withNode(node: CategoryNode): Set<number> {
    const next = new Set(selected.value);
    next.add(node.id);
    descendantIds(node).forEach((id) => next.delete(id));

    return next;
}

// --- Etapas 9c: kategorijų riba pagal planą ---
const initialSet = computed(() => new Set(props.initial ?? []));

// Ar galima pažymėti: rezultatas telpa į ribą arba jame tik jau išsaugotos kategorijos.
// Pažymėjus visą grupę jos vaikai dingsta iš sąrašo, todėl grupė gali tilpti net pasiekus ribą.
function canSelect(node: CategoryNode): boolean {
    if (selectedSet.value.has(node.id)) {
        return true;
    }

    const next = withNode(node);

    return (
        next.size <= props.max ||
        [...next].every((id) => initialSet.value.has(id))
    );
}

function isDisabled(node: CategoryNode): boolean {
    return isCovered(node.id) || !canSelect(node);
}

function toggle(node: CategoryNode, value: boolean | 'indeterminate'): void {
    if (value !== true) {
        remove(node.id);
    } else if (canSelect(node)) {
        selected.value = [...withNode(node)];
    }
}

function remove(id: number): void {
    selected.value = selected.value.filter((selectedId) => selectedId !== id);
}

function selectedCount(node: CategoryNode): number {
    return [node.id, ...descendantIds(node)].filter((id) =>
        selectedSet.value.has(id),
    ).length;
}

// Išskleistos 1 lygio grupės: iš pradžių tos, kuriose kas nors pažymėta
const expanded = ref(
    new Set(
        props.tree
            .filter((node) => selectedCount(node) > 0)
            .map((node) => node.id),
    ),
);

function toggleExpanded(id: number): void {
    const next = new Set(expanded.value);

    if (next.has(id)) {
        next.delete(id);
    } else {
        next.add(id);
    }

    expanded.value = next;
}

// Paieška: rodom mazgus, kurių pavadinime yra frazė, ir jų tėvus
const query = ref('');
const normalizedQuery = computed(() =>
    query.value.trim().toLocaleLowerCase('lt'),
);

function matches(node: CategoryNode): boolean {
    return (
        node.name.toLocaleLowerCase('lt').includes(normalizedQuery.value) ||
        node.children.some(matches)
    );
}

function visible(nodes: CategoryNode[]): CategoryNode[] {
    return normalizedQuery.value === '' ? nodes : nodes.filter(matches);
}

function isExpanded(node: CategoryNode): boolean {
    return normalizedQuery.value !== '' || expanded.value.has(node.id);
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative w-full max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="query"
                    type="search"
                    class="pl-8"
                    placeholder="Ieškoti kategorijos…"
                    aria-label="Ieškoti kategorijos"
                />
            </div>
            <p
                class="text-sm"
                :class="
                    selected.length > max
                        ? 'text-destructive'
                        : 'text-muted-foreground'
                "
            >
                Pasirinkta: {{ selected.length }} iš {{ max }}
            </p>
        </div>

        <ul
            v-if="selected.length"
            class="flex flex-wrap gap-2"
            aria-label="Pasirinktos kategorijos"
        >
            <li
                v-for="id in selected"
                :key="id"
                class="flex items-center gap-1 rounded-full border bg-muted/50 py-0.5 pr-1 pl-3 text-xs"
            >
                {{ nameOf.get(id) }}
                <button
                    type="button"
                    class="rounded-full p-0.5 hover:bg-accent"
                    :aria-label="`Pašalinti ${nameOf.get(id)}`"
                    @click="remove(id)"
                >
                    <X class="size-3" />
                </button>
            </li>
        </ul>

        <ul class="divide-y rounded-lg border">
            <li v-for="root in visible(tree)" :key="root.id">
                <div class="flex items-center gap-3 px-3 py-2">
                    <Checkbox
                        :id="`category-${root.id}`"
                        :model-value="state(root)"
                        :disabled="isDisabled(root)"
                        @update:model-value="toggle(root, $event)"
                    />
                    <label
                        :for="`category-${root.id}`"
                        class="flex-1 cursor-pointer font-medium"
                        >{{ root.name }}</label
                    >
                    <span
                        v-if="selectedCount(root)"
                        class="text-xs text-muted-foreground"
                        >pasirinkta {{ selectedCount(root) }}</span
                    >
                    <button
                        type="button"
                        class="rounded p-1 hover:bg-accent"
                        :aria-expanded="isExpanded(root)"
                        :aria-label="`Rodyti ${root.name} kategorijas`"
                        @click="toggleExpanded(root.id)"
                    >
                        <ChevronRight
                            class="size-4 transition-transform"
                            :class="{ 'rotate-90': isExpanded(root) }"
                        />
                    </button>
                </div>

                <div
                    v-if="isExpanded(root)"
                    class="space-y-3 bg-muted/30 px-3 pt-1 pb-3 sm:pl-10"
                >
                    <div
                        v-for="group in visible(root.children)"
                        :key="group.id"
                    >
                        <div class="flex items-center gap-2 py-1">
                            <Checkbox
                                :id="`category-${group.id}`"
                                :model-value="state(group)"
                                :disabled="isDisabled(group)"
                                @update:model-value="toggle(group, $event)"
                            />
                            <label
                                :for="`category-${group.id}`"
                                class="cursor-pointer text-sm font-medium"
                                >{{ group.name }}
                                <span
                                    v-if="group.children.length"
                                    class="font-normal text-muted-foreground"
                                    >(visa grupė)</span
                                ></label
                            >
                        </div>
                        <div
                            v-if="group.children.length"
                            class="grid gap-x-4 gap-y-1 pl-6 sm:grid-cols-2"
                        >
                            <div
                                v-for="leaf in visible(group.children)"
                                :key="leaf.id"
                                class="flex items-center gap-2"
                            >
                                <Checkbox
                                    :id="`category-${leaf.id}`"
                                    :model-value="state(leaf)"
                                    :disabled="isDisabled(leaf)"
                                    @update:model-value="toggle(leaf, $event)"
                                />
                                <label
                                    :for="`category-${leaf.id}`"
                                    class="cursor-pointer text-sm"
                                    >{{ leaf.name }}</label
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>

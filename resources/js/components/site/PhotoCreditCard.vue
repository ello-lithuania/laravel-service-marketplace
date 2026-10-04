<script setup lang="ts">
import { computed } from 'vue';
import type { PhotoCreditItem } from '@/types/site';

// Nuotraukos kortelė: miniatiūra + autorius, šaltinis, licencija (su nuorodomis, jei jos žinomos).
// Nuorodos į kitas svetaines: target="_blank" + rel="noopener" (atidarytas puslapis negali valdyti mūsų lango),
// nofollow – tai ne mūsų rekomendacija paieškos sistemoms.
const props = defineProps<{ item: PhotoCreditItem }>();

const rows = computed(() => [
    {
        label: 'Autorius',
        text: props.item.author ?? 'nenurodytas',
        url: props.item.author_url,
    },
    {
        label: 'Šaltinis',
        text: props.item.source,
        url: props.item.source_url,
    },
    {
        label: 'Licencija',
        text: props.item.license,
        url: props.item.license_url,
    },
]);
</script>

<template>
    <figure class="h-full overflow-hidden rounded-lg border bg-card">
        <img
            :src="item.thumb_url"
            :alt="item.title ?? item.label ?? ''"
            loading="lazy"
            width="480"
            height="360"
            class="aspect-[4/3] w-full bg-muted object-cover"
        />
        <figcaption class="space-y-2 p-4 text-sm">
            <p v-if="item.label" class="font-medium">{{ item.label }}</p>
            <p v-if="item.title" class="line-clamp-2 text-muted-foreground">
                „{{ item.title }}“
            </p>
            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                <template v-for="row in rows" :key="row.label">
                    <dt class="text-muted-foreground">{{ row.label }}</dt>
                    <dd class="min-w-0 break-words">
                        <a
                            v-if="row.url"
                            :href="row.url"
                            target="_blank"
                            rel="noopener nofollow"
                            class="underline underline-offset-2 hover:text-primary"
                            >{{ row.text }}</a
                        >
                        <span v-else>{{ row.text }}</span>
                    </dd>
                </template>
            </dl>
        </figcaption>
    </figure>
</template>

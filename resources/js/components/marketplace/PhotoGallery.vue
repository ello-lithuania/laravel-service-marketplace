<script setup lang="ts">
import { Trash2 } from '@lucide/vue';
import type { PrivateFile } from '@/types';

// Užklausos nuotraukų miniatiūros; paspaudus – didelė nuotrauka naujame skirtuke. deletable – su šalinimo mygtuku
defineProps<{ photos: PrivateFile[]; deletable?: boolean }>();
defineEmits<{ delete: [photo: PrivateFile] }>();
</script>

<template>
    <ul
        v-if="photos.length"
        class="grid grid-cols-2 gap-2 sm:grid-cols-4"
        data-test="request-photos"
    >
        <li
            v-for="photo in photos"
            :key="photo.id"
            class="group relative overflow-hidden rounded-lg border bg-muted"
        >
            <a :href="photo.url" target="_blank" rel="noopener">
                <img
                    :src="photo.thumb_url ?? photo.url"
                    :alt="photo.name"
                    loading="lazy"
                    class="aspect-[4/3] w-full object-cover transition-transform group-hover:scale-105"
                />
            </a>
            <button
                v-if="deletable"
                type="button"
                class="absolute top-1.5 right-1.5 rounded-full bg-background/90 p-1.5 text-destructive shadow hover:bg-background"
                :aria-label="`Pašalinti ${photo.name}`"
                @click="$emit('delete', photo)"
            >
                <Trash2 class="size-3.5" />
            </button>
        </li>
    </ul>
</template>

<script setup lang="ts">
import { FileText } from '@lucide/vue';
import { computed } from 'vue';
import { formatFileSize } from '@/lib/messages';
import type { PrivateFile } from '@/types';

// Žinutės priedai: nuotraukos – miniatiūros, PDF – failo „kortelė". Nuorodos veda per /failai/{id} (su teisių patikra)
const props = defineProps<{ files: PrivateFile[]; mine?: boolean }>();

const images = computed(() => props.files.filter((file) => file.is_image));
const documents = computed(() => props.files.filter((file) => !file.is_image));
</script>

<template>
    <div v-if="files.length" class="flex flex-col gap-2">
        <div v-if="images.length" class="flex flex-wrap gap-2">
            <a
                v-for="image in images"
                :key="image.id"
                :href="image.url"
                target="_blank"
                rel="noopener"
                class="block overflow-hidden rounded-lg border bg-background"
                data-test="attachment-image"
            >
                <img
                    :src="image.thumb_url ?? image.url"
                    :alt="image.name"
                    loading="lazy"
                    class="h-28 w-36 object-cover"
                />
            </a>
        </div>
        <a
            v-for="doc in documents"
            :key="doc.id"
            :href="doc.url"
            class="inline-flex max-w-full items-center gap-2 rounded-lg border px-3 py-2 text-sm hover:bg-muted"
            :class="mine ? 'bg-primary/5' : 'bg-background'"
            data-test="attachment-file"
        >
            <FileText class="size-4 shrink-0 text-red-600" />
            <span class="truncate">{{ doc.name }}</span>
            <span class="shrink-0 text-xs text-muted-foreground">
                {{ formatFileSize(doc.size) }}
            </span>
        </a>
    </div>
</template>

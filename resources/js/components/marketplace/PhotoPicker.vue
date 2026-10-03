<script setup lang="ts">
import { ImagePlus, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { IMAGE_TYPES, MAX_IMAGE_BYTES } from '@/lib/messages';

/*
 * Nuotraukų pasirinkimas (dar neįkeltų): peržiūra naršyklėje, pašalinimas, ribos.
 * Ribos – tos pačios kaip serverio (ImageValidationRules); čia tik tam, kad klaidą matytum iš karto.
 */
const files = defineModel<File[]>({ default: () => [] });
const props = defineProps<{
    /** Kiek dar galima pridėti (MAX minus jau įkeltos) */
    max: number;
    error?: string;
}>();

const input = useTemplateRef<HTMLInputElement>('input');
const previews = ref<string[]>([]);
const clientError = ref<string | null>(null);
const remaining = computed(() => props.max - files.value.length);

// Formai išsivalius (reset) – išvalom ir peržiūras
watch(
    () => files.value.length,
    (length) => {
        if (length === 0 && previews.value.length) {
            previews.value.forEach((url) => URL.revokeObjectURL(url));
            previews.value = [];
        }
    },
);

function add(event: Event): void {
    clientError.value = null;

    for (const file of Array.from(
        (event.target as HTMLInputElement).files ?? [],
    )) {
        if (remaining.value <= 0) {
            clientError.value = `Daugiausia ${props.max} nuotraukų.`;
            break;
        }

        if (!IMAGE_TYPES.includes(file.type)) {
            clientError.value = `„${file.name}“ – tinka tik JPG, PNG arba WEBP.`;
            continue;
        }

        if (file.size > MAX_IMAGE_BYTES) {
            clientError.value = `„${file.name}“ per didelė – daugiausia 5 MB.`;
            continue;
        }

        files.value = [...files.value, file];
        previews.value.push(URL.createObjectURL(file));
    }

    if (input.value) {
        input.value.value = '';
    }
}

function remove(index: number): void {
    URL.revokeObjectURL(previews.value[index]);
    previews.value.splice(index, 1);
    files.value = files.value.filter((_, i) => i !== index);
}

onBeforeUnmount(() =>
    previews.value.forEach((url) => URL.revokeObjectURL(url)),
);
</script>

<template>
    <div class="space-y-3">
        <ul
            v-if="previews.length"
            class="grid grid-cols-3 gap-2 sm:grid-cols-4"
        >
            <li
                v-for="(url, i) in previews"
                :key="url"
                class="relative overflow-hidden rounded-lg border"
            >
                <img
                    :src="url"
                    :alt="files[i]?.name"
                    class="aspect-[4/3] w-full object-cover"
                />
                <button
                    type="button"
                    class="absolute top-1 right-1 rounded-full bg-background/90 p-1 shadow"
                    :aria-label="`Pašalinti ${files[i]?.name}`"
                    @click="remove(i)"
                >
                    <X class="size-3.5" />
                </button>
            </li>
        </ul>

        <input
            ref="input"
            type="file"
            multiple
            accept="image/jpeg,image/png,image/webp"
            class="sr-only"
            data-test="photo-input"
            @change="add"
        />
        <Button
            type="button"
            variant="outline"
            size="sm"
            :disabled="remaining <= 0"
            @click="input?.click()"
        >
            <ImagePlus class="size-4" /> Pridėti nuotraukų
        </Button>
        <p class="text-xs text-muted-foreground">
            JPG, PNG arba WEBP, iki 5 MB. Galima dar
            {{ Math.max(0, remaining) }}. Nuotraukas matys tik teikėjai, kuriems
            tinka jūsų užklausa.
        </p>
        <InputError :message="clientError ?? error" />
    </div>
</template>

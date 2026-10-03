<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { computed } from 'vue';
import PhotoGallery from '@/components/marketplace/PhotoGallery.vue';
import PhotoPicker from '@/components/marketplace/PhotoPicker.vue';
import { Button } from '@/components/ui/button';
import { destroy, store } from '@/routes/service-requests/photos';
import type { PrivateFile } from '@/types';

// Kliento užklausos nuotraukų valdymas (kol užklausa laukia patvirtinimo ar pasiūlymų)
const props = defineProps<{
    slug: string;
    photos: PrivateFile[];
    max: number;
}>();

const form = useForm({ photos: [] as File[] });
const remaining = computed(() => props.max - props.photos.length);

// Klaidos apie konkretų failą ateina kaip „photos.0", „photos.1"…
const photosError = computed(
    () =>
        form.errors.photos ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('photos.'),
        )?.[1],
);

function upload(): void {
    form.post(store(props.slug).url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(photo: PrivateFile): void {
    if (!window.confirm('Pašalinti šią nuotrauką?')) {
        return;
    }

    router.delete(
        destroy({ serviceRequest: props.slug, media: photo.id }).url,
        { preserveScroll: true },
    );
}
</script>

<template>
    <div class="space-y-3" data-test="photos-manager">
        <p class="text-sm text-muted-foreground">
            Nuotraukos ({{ photos.length }} / {{ max }})
        </p>
        <PhotoGallery :photos="photos" deletable @delete="remove" />
        <form v-if="remaining > 0" class="space-y-3" @submit.prevent="upload">
            <PhotoPicker
                v-model="form.photos"
                :max="remaining"
                :error="photosError"
            />
            <Button
                v-if="form.photos.length"
                type="submit"
                size="sm"
                :disabled="form.processing"
            >
                <Upload class="size-4" /> Įkelti ({{ form.photos.length }})
            </Button>
        </form>
    </div>
</template>

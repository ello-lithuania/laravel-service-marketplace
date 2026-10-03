<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ImageUp, Trash2 } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

// Vienos nuotraukos (avataro, logotipo, viršelio) įkėlimas: pasirinkus failą jis iškart siunčiamas
// serveriui, o po atsakymo Inertia atnaujina puslapį su nauju paveikslėlio URL.
const props = withDefaults(
    defineProps<{
        label: string;
        description?: string;
        url: string | null;
        uploadUrl: string;
        deleteUrl: string;
        shape?: 'round' | 'square' | 'wide';
    }>(),
    { description: undefined, shape: 'square' },
);

/** Turi sutapti su App\Concerns\ImageValidationRules (5 MB) */
const MAX_BYTES = 5 * 1024 * 1024;

const input = useTemplateRef<HTMLInputElement>('input');
const form = useForm<{ image: File | null }>({ image: null });

const previewClass = {
    round: 'size-20 rounded-full',
    square: 'size-24 rounded-lg',
    wide: 'aspect-[3/1] w-full max-w-md rounded-lg',
}[props.shape];

function upload(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form.clearErrors();

    // Patikra naršyklėje – kad žmogui nereikėtų laukti, kol didelis failas nukeliaus į serverį.
    // Serveris vis tiek tikrina pats: naršyklės patikrą lengva apeiti.
    if (file.size > MAX_BYTES) {
        form.setError('image', 'Nuotrauka per didelė – daugiausia 5 MB.');
        resetInput();

        return;
    }

    form.image = file;
    // Su failu Inertia siunčia multipart/form-data (FormData), kitaip – JSON
    form.post(props.uploadUrl, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            form.reset();
            resetInput();
        },
    });
}

function remove(): void {
    router.delete(props.deleteUrl, { preserveScroll: true });
}

function resetInput(): void {
    if (input.value) {
        input.value.value = '';
    }
}
</script>

<template>
    <div class="space-y-3">
        <div>
            <p class="text-sm font-medium">{{ label }}</p>
            <p v-if="description" class="text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            <div
                class="flex shrink-0 items-center justify-center overflow-hidden border bg-muted text-muted-foreground"
                :class="previewClass"
            >
                <img
                    v-if="url"
                    :src="url"
                    :alt="label"
                    class="size-full object-cover"
                />
                <ImageUp v-else class="size-6" aria-hidden="true" />
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="form.processing"
                    @click="input?.click()"
                >
                    <Spinner v-if="form.processing" />
                    {{ url ? 'Pakeisti' : 'Įkelti' }}
                </Button>
                <Button
                    v-if="url"
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="form.processing"
                    @click="remove"
                >
                    <Trash2 class="size-4" />
                    Pašalinti
                </Button>
                <input
                    ref="input"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    @change="upload"
                />
            </div>
        </div>

        <p class="text-xs text-muted-foreground">
            JPG, PNG arba WEBP, iki 5 MB, ne mažesnė nei 200×200 taškų.
        </p>
        <InputError :message="form.errors.image" />
    </div>
</template>

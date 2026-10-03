<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import FormTextarea from '@/components/marketplace/FormTextarea.vue';
import StarRatingInput from '@/components/reviews/StarRatingInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

// Atsiliepimo forma: patvirtintam (užklausos puslapyje) ir pagal pakvietimą. action – kur siųsti
const props = defineProps<{ action: string; submitLabel?: string }>();

const form = useForm({ rating: null as number | null, comment: '' });

function submit(): void {
    form.post(props.action, { preserveScroll: true });
}

// Verslo taisyklių klaidos (pvz. „jau įvertinote") ateina raktu „review"
function ruleError(): string | undefined {
    return (form.errors as Record<string, string | undefined>).review;
}
</script>

<template>
    <form class="space-y-4" data-test="review-form" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label>Įvertinimas</Label>
            <StarRatingInput
                v-model="form.rating"
                :invalid="!!form.errors.rating"
            />
            <InputError :message="form.errors.rating" />
        </div>
        <div class="grid gap-2">
            <Label for="comment">Atsiliepimas</Label>
            <FormTextarea
                id="comment"
                v-model="form.comment"
                :rows="5"
                :maxlength="2000"
                :invalid="!!form.errors.comment"
                placeholder="Kaip sekėsi? Ar darbas atliktas kokybiškai ir laiku? Ką patartumėte kitiems?"
            />
            <InputError :message="form.errors.comment" />
        </div>
        <InputError :message="ruleError()" />
        <Button type="submit" :disabled="form.processing">
            {{ submitLabel ?? 'Paskelbti atsiliepimą' }}
        </Button>
    </form>
</template>

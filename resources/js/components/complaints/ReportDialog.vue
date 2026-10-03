<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Flag } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import FormTextarea from '@/components/marketplace/FormTextarea.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/complaints';
import type { ComplaintReason, ReportableType } from '@/types';

/*
 * „Pranešti apie pažeidimą": skundas dėl užklausos, pasiūlymo, atsiliepimo, žinutės ar profilio.
 * Serveris dar kartą patikrina, ar galima (ComplaintPolicy), ir ar apie tai jau nepranešta.
 */
// Dialog šaknis DOM elemento neturi, todėl class ir kiti atributai perduodami mygtukui
defineOptions({ inheritAttrs: false });

const props = withDefaults(
    defineProps<{
        type: ReportableType;
        id: number;
        /** Mažas mygtukas (tik ikona) – pvz. prie žinutės */
        compact?: boolean;
    }>(),
    { compact: false },
);

// Priežastys – kaip App\Enums\ComplaintReason. „Netikras atsiliepimas" tinka tik atsiliepimams
const REASONS: { value: ComplaintReason; label: string }[] = [
    { value: 'spam', label: 'Šlamštas' },
    { value: 'fraud', label: 'Sukčiavimas' },
    { value: 'offensive', label: 'Įžeidžiantis turinys' },
    { value: 'fake_review', label: 'Netikras atsiliepimas' },
    { value: 'wrong_info', label: 'Klaidinga informacija' },
    { value: 'other', label: 'Kita' },
];

const SUBJECTS: Record<ReportableType, string> = {
    service_request: 'užklausą',
    offer: 'pasiūlymą',
    review: 'atsiliepimą',
    message: 'žinutę',
    provider_profile: 'teikėjo profilį',
};

const reasons = computed(() =>
    REASONS.filter(
        (reason) => reason.value !== 'fake_review' || props.type === 'review',
    ),
);

const open = ref(false);
const form = useForm({
    type: props.type,
    id: props.id,
    reason: null as ComplaintReason | null,
    description: '',
});

// Verslo taisyklių klaidos (pvz. „jau pranešėte") ateina raktu „complaint"
const ruleError = computed(
    () =>
        (form.errors as Record<string, string | undefined>).complaint ??
        form.errors.id,
);

function submit(): void {
    form.post(store().url, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset('reason', 'description');
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                v-bind="$attrs"
                variant="ghost"
                :size="compact ? 'icon' : 'sm'"
                class="text-muted-foreground hover:text-destructive"
                :class="{ 'size-7': compact }"
                :aria-label="`Pranešti apie ${SUBJECTS[type]}`"
                :title="`Pranešti apie ${SUBJECTS[type]}`"
                data-test="report-button"
            >
                <Flag class="size-4" />
                <span v-if="!compact">Pranešti</span>
            </Button>
        </DialogTrigger>
        <DialogContent>
            <form class="space-y-5" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle
                        >Pranešti apie {{ SUBJECTS[type] }}</DialogTitle
                    >
                    <DialogDescription>
                        Pranešimą matys tik administratorius. Jei pažeidimas
                        pasitvirtins, turinys bus paslėptas.
                    </DialogDescription>
                </DialogHeader>

                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Priežastis</legend>
                    <label
                        v-for="reason in reasons"
                        :key="reason.value"
                        class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm has-checked:border-primary has-checked:bg-primary/5"
                    >
                        <input
                            v-model="form.reason"
                            type="radio"
                            name="reason"
                            :value="reason.value"
                            class="accent-primary"
                        />
                        {{ reason.label }}
                    </label>
                    <InputError :message="form.errors.reason" />
                </fieldset>

                <div class="grid gap-2">
                    <Label :for="`complaint-description-${type}-${id}`">
                        Aprašymas{{
                            form.reason === 'other' ? '' : ' (neprivaloma)'
                        }}
                    </Label>
                    <FormTextarea
                        :id="`complaint-description-${type}-${id}`"
                        v-model="form.description"
                        :rows="3"
                        :maxlength="1000"
                        :invalid="!!form.errors.description"
                        placeholder="Kas negerai? Kuo konkretesnis aprašymas, tuo greičiau išnagrinėsime."
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <InputError :message="ruleError" />

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button type="button" variant="secondary">
                            Atšaukti
                        </Button>
                    </DialogClose>
                    <Button
                        type="submit"
                        variant="destructive"
                        :disabled="form.processing || !form.reason"
                        data-test="report-submit"
                    >
                        Pranešti
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

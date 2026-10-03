<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { cn } from '@/lib/utils';
import { index as portfolioIndex } from '@/routes/portfolio';
import { edit as editImages } from '@/routes/provider/images';
import type { WizardStep } from '@/types';

// Teikėjo profilio vedlio „rėmas": antraštė, žingsnių juosta (progresas) ir turinys (slot).
// Žingsniai ateina iš serverio (App\Enums\ProviderWizardStep::progress), todėl
// „atlikta" visada atitinka tikrus duomenis.
const props = defineProps<{
    steps: WizardStep[];
    current: WizardStep['key'] | null;
    title: string;
    description?: string;
}>();

// Kol profilis nesukurtas, kiti žingsniai neprieinami (žr. ProviderWizardStep::progress)
const hasProfile = computed(() => props.steps.every((step) => step.available));
</script>

<template>
    <div class="mx-auto w-full max-w-4xl px-4 py-6">
        <Heading
            title="Teikėjo profilis"
            description="Užpildykite žingsnius bet kuria tvarka – kiekvienas išsaugomas atskirai, todėl galite grįžti vėliau."
        />

        <nav aria-label="Profilio vedlio žingsniai" class="mb-8">
            <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <li v-for="(step, index) in steps" :key="step.key">
                    <component
                        :is="step.available ? Link : 'span'"
                        :href="step.available ? step.href : undefined"
                        :aria-current="
                            step.key === current ? 'step' : undefined
                        "
                        :class="
                            cn(
                                'flex items-center gap-3 rounded-lg border p-3 text-sm transition-colors',
                                step.key === current
                                    ? 'border-primary bg-primary/5'
                                    : step.available
                                      ? 'hover:bg-accent'
                                      : 'cursor-not-allowed opacity-50',
                            )
                        "
                    >
                        <span
                            :class="
                                cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-medium',
                                    step.done &&
                                        'border-primary bg-primary text-primary-foreground',
                                )
                            "
                        >
                            <Check v-if="step.done" class="size-4" />
                            <template v-else>{{ index + 1 }}</template>
                        </span>
                        <span class="leading-tight">
                            <span class="block font-medium">{{
                                step.label
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                step.done
                                    ? 'Atlikta'
                                    : step.required
                                      ? 'Privaloma'
                                      : 'Neprivaloma'
                            }}</span>
                        </span>
                    </component>
                </li>
            </ol>

            <div
                v-if="hasProfile"
                class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
            >
                <span>Taip pat:</span>
                <Link
                    :href="editImages()"
                    class="underline-offset-4 hover:underline"
                    >Logotipas ir viršelis</Link
                >
                <Link
                    :href="portfolioIndex()"
                    class="underline-offset-4 hover:underline"
                    >Atlikti darbai</Link
                >
            </div>
        </nav>

        <section class="space-y-6">
            <Heading
                variant="small"
                :title="title"
                :description="description"
            />
            <slot />
        </section>
    </div>
</template>

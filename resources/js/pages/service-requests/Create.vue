<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Check, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import CategoryPicker from '@/components/marketplace/CategoryPicker.vue';
import FormTextarea from '@/components/marketplace/FormTextarea.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/marketplace';
import { create, index, store } from '@/routes/service-requests';
import type { CategoryNode } from '@/types';

type City = { id: number; name: string; region: string };
type Choice = { value: string; label: string };

const props = defineProps<{
    categories: CategoryNode[];
    cities: City[];
    startPreferences: Choice[];
    defaults: { category_id: number | null; city_id: number | null };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Mano užklausos', href: index() },
            { title: 'Nauja užklausa', href: create() },
        ],
    },
});

/*
 * Daugiažingsnė forma: visi laukai vienoje useForm būsenoje, o žingsniai tik rodo jų dalį.
 * „Toliau" siunčia Precognition užklausą – serveris patikrina TIK to žingsnio laukus tomis pačiomis
 * StoreServiceRequestRequest taisyklėmis (nieko neišsaugodamas). Taisyklių nereikia dubliuoti JavaScript'e.
 * https://inertiajs.com/forms#precognition · https://laravel.com/docs/13.x/precognition
 */
const form = useForm(store(), {
    category_id: props.defaults.category_id,
    title: '',
    description: '',
    city_id: props.defaults.city_id,
    address: '',
    start_preference: 'flexible',
    start_date: '',
    budget_min: '',
    budget_max: '',
}).setValidationTimeout(300);

type Field = keyof ReturnType<typeof form.data>;

const steps: { title: string; fields: Field[] }[] = [
    { title: 'Paslauga', fields: ['category_id'] },
    { title: 'Darbo aprašymas', fields: ['title', 'description'] },
    {
        title: 'Vieta ir laikas',
        fields: ['city_id', 'address', 'start_preference', 'start_date'],
    },
    { title: 'Biudžetas ir peržiūra', fields: ['budget_min', 'budget_max'] },
];

const step = ref(0);
const isLast = computed(() => step.value === steps.length - 1);

// Miestai sugrupuoti pagal apskritį (<optgroup>)
const citiesByRegion = computed(() => {
    const groups = new Map<string, City[]>();

    for (const city of props.cities) {
        groups.set(city.region, [...(groups.get(city.region) ?? []), city]);
    }

    return [...groups.entries()];
});

const leafNames = computed(() => {
    const names = new Map<number, string>();

    props.categories.forEach((root) =>
        root.children.forEach((group) =>
            group.children.forEach((leaf) => names.set(leaf.id, leaf.name)),
        ),
    );

    return names;
});

const summary = computed(() => [
    {
        label: 'Paslauga',
        value: leafNames.value.get(form.category_id ?? 0) ?? '–',
    },
    { label: 'Pavadinimas', value: form.title || '–' },
    {
        label: 'Vieta',
        value: props.cities.find((c) => c.id === form.city_id)?.name ?? '–',
    },
    {
        label: 'Pradžia',
        value:
            form.start_preference === 'date' && form.start_date
                ? form.start_date
                : (props.startPreferences.find(
                      (p) => p.value === form.start_preference,
                  )?.label ?? '–'),
    },
    {
        label: 'Biudžetas',
        value: budgetText(),
    },
]);

function budgetText(): string {
    const min = Number(form.budget_min) || null;
    const max = Number(form.budget_max) || null;

    if (min && max) {
        return `${formatMoney(min * 100)} – ${formatMoney(max * 100)}`;
    }

    if (min) {
        return `nuo ${formatMoney(min * 100)}`;
    }

    return max ? `iki ${formatMoney(max * 100)}` : 'Nenurodytas';
}

function next(): void {
    form.validate({
        only: steps[step.value].fields,
        onSuccess: () => {
            step.value++;
        },
    });
}

function back(): void {
    step.value = Math.max(0, step.value - 1);
}

function submit(): void {
    form.submit({
        // Jei serveris rado klaidų ankstesniame žingsnyje (pvz. kategorija išjungta) – grįžtam į jį
        onError: (errors) => {
            const failed = steps.findIndex((s) =>
                s.fields.some((field) => field in errors),
            );

            if (failed !== -1) {
                step.value = failed;
            }
        },
    });
}
</script>

<template>
    <Head title="Nauja užklausa" />

    <div class="mx-auto w-full max-w-3xl space-y-6 p-4 md:p-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight">
                Nauja užklausa
            </h1>
            <p class="text-sm text-muted-foreground">
                Aprašykite darbą – tinkami meistrai gaus pranešimą ir atsiųs
                pasiūlymus. Užklausa nemokama.
            </p>
        </header>

        <!-- Žingsnių juosta -->
        <ol class="grid grid-cols-4 gap-2" aria-label="Formos žingsniai">
            <li
                v-for="(item, i) in steps"
                :key="item.title"
                class="space-y-1.5"
                :aria-current="i === step ? 'step' : undefined"
            >
                <div
                    class="h-1.5 rounded-full"
                    :class="i <= step ? 'bg-primary' : 'bg-muted'"
                />
                <p
                    class="hidden text-xs sm:block"
                    :class="
                        i === step
                            ? 'font-medium text-foreground'
                            : 'text-muted-foreground'
                    "
                >
                    {{ i + 1 }}. {{ item.title }}
                </p>
            </li>
        </ol>

        <form
            class="space-y-6 rounded-xl border bg-card p-4 md:p-6"
            @submit.prevent="isLast ? submit() : next()"
        >
            <h2 class="text-lg font-semibold">
                {{ step + 1 }}. {{ steps[step].title }}
            </h2>

            <!-- 1. Paslauga -->
            <div v-show="step === 0" class="space-y-2">
                <Label>Kokios paslaugos reikia?</Label>
                <CategoryPicker
                    v-model="form.category_id"
                    :categories="categories"
                    :invalid="!!form.errors.category_id"
                />
                <InputError :message="form.errors.category_id" />
            </div>

            <!-- 2. Aprašymas -->
            <div v-show="step === 1" class="space-y-5">
                <div class="grid gap-2">
                    <Label for="title">Trumpas pavadinimas</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        maxlength="150"
                        placeholder="Pvz. „Plytelių klijavimas vonios kambaryje“"
                        :aria-invalid="!!form.errors.title || undefined"
                    />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="description">Darbo aprašymas</Label>
                    <FormTextarea
                        id="description"
                        v-model="form.description"
                        :rows="7"
                        :maxlength="5000"
                        :invalid="!!form.errors.description"
                        placeholder="Ką reikia padaryti? Koks plotas ar kiekis? Ar turite medžiagų? Kas svarbu?"
                    />
                    <p class="text-xs text-muted-foreground">
                        Kontaktų (telefono, el. pašto, nuorodų) nerašykite –
                        juos teikėjas gaus, kai priimsite jo pasiūlymą.
                    </p>
                    <InputError :message="form.errors.description" />
                </div>
            </div>

            <!-- 3. Vieta ir laikas -->
            <div v-show="step === 2" class="space-y-5">
                <div class="grid gap-2">
                    <Label for="city_id">Miestas / rajonas</Label>
                    <select
                        id="city_id"
                        v-model="form.city_id"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                        :aria-invalid="!!form.errors.city_id || undefined"
                    >
                        <option :value="null" disabled>Pasirinkite…</option>
                        <optgroup
                            v-for="[region, list] in citiesByRegion"
                            :key="region"
                            :label="region"
                        >
                            <option
                                v-for="city in list"
                                :key="city.id"
                                :value="city.id"
                            >
                                {{ city.name }}
                            </option>
                        </optgroup>
                    </select>
                    <InputError :message="form.errors.city_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="address">Adresas (neprivaloma)</Label>
                    <Input
                        id="address"
                        v-model="form.address"
                        maxlength="255"
                        placeholder="Gatvė, namo numeris"
                    />
                    <p class="text-xs text-muted-foreground">
                        Adresą matysite tik jūs ir teikėjas, kurio pasiūlymą
                        priimsite.
                    </p>
                    <InputError :message="form.errors.address" />
                </div>
                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">
                        Kada norite pradėti?
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="option in startPreferences"
                            :key="option.value"
                            class="cursor-pointer rounded-full border px-3 py-1.5 text-sm has-checked:border-primary has-checked:bg-primary/10"
                        >
                            <input
                                v-model="form.start_preference"
                                type="radio"
                                class="sr-only"
                                name="start_preference"
                                :value="option.value"
                            />
                            {{ option.label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.start_preference" />
                </fieldset>
                <div v-if="form.start_preference === 'date'" class="grid gap-2">
                    <Label for="start_date">Data</Label>
                    <Input
                        id="start_date"
                        v-model="form.start_date"
                        type="date"
                        class="w-48"
                    />
                    <InputError :message="form.errors.start_date" />
                </div>
            </div>

            <!-- 4. Biudžetas ir peržiūra -->
            <div v-show="step === 3" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="budget_min">Biudžetas nuo (€)</Label>
                        <Input
                            id="budget_min"
                            v-model="form.budget_min"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                        />
                        <InputError :message="form.errors.budget_min" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="budget_max">Biudžetas iki (€)</Label>
                        <Input
                            id="budget_max"
                            v-model="form.budget_max"
                            type="number"
                            min="1"
                            step="1"
                            inputmode="numeric"
                        />
                        <InputError :message="form.errors.budget_max" />
                    </div>
                </div>
                <p class="-mt-3 text-xs text-muted-foreground">
                    Neprivaloma, bet su biudžetu pasiūlymai būna tikslesni.
                </p>

                <dl class="divide-y rounded-lg border bg-muted/30 text-sm">
                    <div
                        v-for="row in summary"
                        :key="row.label"
                        class="flex justify-between gap-4 px-3 py-2"
                    >
                        <dt class="text-muted-foreground">{{ row.label }}</dt>
                        <dd class="text-right font-medium">{{ row.value }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flex items-center justify-between gap-3 border-t pt-4">
                <Button
                    v-if="step > 0"
                    type="button"
                    variant="ghost"
                    @click="back"
                >
                    <ChevronLeft class="size-4" /> Atgal
                </Button>
                <span v-else />

                <Button
                    type="submit"
                    :disabled="form.processing || form.validating"
                    data-test="service-request-next"
                >
                    <Spinner v-if="form.processing || form.validating" />
                    <template v-if="isLast">
                        <Check class="size-4" /> Paskelbti užklausą
                    </template>
                    <template v-else>
                        Toliau <ChevronRight class="size-4" />
                    </template>
                </Button>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ProviderWizard from '@/components/account/ProviderWizard.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { wizard as wizardRoute } from '@/routes/provider';
import { update } from '@/routes/provider/details';
import type { RegionOption, SelectOption, WizardStep } from '@/types';

type ProfileDetails = {
    type: 'individual' | 'company';
    display_name: string;
    company_code: string | null;
    vat_code: string | null;
    city_id: number;
    years_experience: number | null;
    headline: string | null;
    description: string | null;
    website: string | null;
};

const props = defineProps<{
    wizard: WizardStep[];
    /** null – profilis dar nesukurtas (vedlys pradedamas) */
    profile: ProfileDetails | null;
    phone: string | null;
    suggestedName: string;
    types: SelectOption[];
    regions: RegionOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teikėjo profilis', href: wizardRoute() }],
    },
});

// useForm – reaktyvi formos būsena: duomenys, klaidos, processing. Tinka sudėtingesnėms formoms,
// kai laukai priklauso vienas nuo kito (pvz. įmonės kodas rodomas tik įmonei).
// https://inertiajs.com/forms#form-helper
const form = useForm({
    type: props.profile?.type ?? 'individual',
    display_name: props.profile?.display_name ?? props.suggestedName,
    company_code: props.profile?.company_code ?? '',
    vat_code: props.profile?.vat_code ?? '',
    phone: props.phone ?? '',
    city_id: props.profile?.city_id ?? null,
    // Tuščias laukas siunčiamas kaip '', o Laravel jį paverčia null (ConvertEmptyStringsToNull)
    years_experience: props.profile?.years_experience ?? '',
    headline: props.profile?.headline ?? '',
    description: props.profile?.description ?? '',
    website: props.profile?.website ?? '',
});

function submit(): void {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Teikėjo profilis – duomenys" />

    <ProviderWizard
        :steps="wizard"
        current="details"
        title="1. Duomenys"
        description="Kaip klientai jus matys ir kaip su jumis susisieks."
    >
        <form class="space-y-6" @submit.prevent="submit">
            <fieldset class="grid gap-2">
                <legend class="mb-2 text-sm font-medium">Teikėjo tipas</legend>
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="option in types"
                        :key="option.value"
                        class="flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2 text-sm has-checked:border-primary has-checked:bg-primary/5"
                    >
                        <input
                            v-model="form.type"
                            type="radio"
                            name="type"
                            :value="option.value"
                            class="accent-primary"
                        />
                        {{ option.label }}
                    </label>
                </div>
                <InputError :message="form.errors.type" />
            </fieldset>

            <div class="grid gap-2">
                <Label for="display_name">{{
                    form.type === 'company'
                        ? 'Įmonės pavadinimas'
                        : 'Vardas ir pavardė arba veiklos pavadinimas'
                }}</Label>
                <Input
                    id="display_name"
                    v-model="form.display_name"
                    required
                    maxlength="150"
                />
                <p class="text-xs text-muted-foreground">
                    Iš pavadinimo sukuriamas jūsų profilio adresas, pvz.
                    /meistrai/jonas-petraitis. Vėliau adresas nesikeičia.
                </p>
                <InputError :message="form.errors.display_name" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div v-if="form.type === 'company'" class="grid gap-2">
                    <Label for="company_code">Įmonės kodas</Label>
                    <Input
                        id="company_code"
                        v-model="form.company_code"
                        inputmode="numeric"
                        placeholder="123456789"
                    />
                    <InputError :message="form.errors.company_code" />
                </div>
                <div class="grid gap-2">
                    <Label for="vat_code">PVM mokėtojo kodas (jei esate)</Label>
                    <Input
                        id="vat_code"
                        v-model="form.vat_code"
                        placeholder="LT123456789"
                    />
                    <InputError :message="form.errors.vat_code" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="phone">Telefonas</Label>
                    <Input
                        id="phone"
                        v-model="form.phone"
                        type="tel"
                        autocomplete="tel"
                        placeholder="+370 612 34567"
                        required
                    />
                    <InputError :message="form.errors.phone" />
                </div>
                <div class="grid gap-2">
                    <Label for="city_id">Bazinis miestas ar rajonas</Label>
                    <NativeSelect id="city_id" v-model="form.city_id" required>
                        <option :value="null" disabled>Pasirinkite…</option>
                        <optgroup
                            v-for="region in regions"
                            :key="region.id"
                            :label="region.name"
                        >
                            <option
                                v-for="city in region.cities"
                                :key="city.id"
                                :value="city.id"
                            >
                                {{ city.name }}
                            </option>
                        </optgroup>
                    </NativeSelect>
                    <InputError :message="form.errors.city_id" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="years_experience">Patirtis (metais)</Label>
                    <Input
                        id="years_experience"
                        v-model="form.years_experience"
                        type="number"
                        min="0"
                        max="80"
                    />
                    <InputError :message="form.errors.years_experience" />
                </div>
                <div class="grid gap-2">
                    <Label for="website">Svetainė</Label>
                    <Input
                        id="website"
                        v-model="form.website"
                        placeholder="www.jusu-svetaine.lt"
                    />
                    <InputError :message="form.errors.website" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="headline">Trumpas prisistatymas</Label>
                <Input
                    id="headline"
                    v-model="form.headline"
                    maxlength="160"
                    placeholder="Plytelių klijavimas Vilniuje, 10 metų patirtis"
                />
                <InputError :message="form.errors.headline" />
            </div>

            <div class="grid gap-2">
                <Label for="description">Aprašymas</Label>
                <Textarea
                    id="description"
                    v-model="form.description"
                    rows="6"
                    maxlength="5000"
                    placeholder="Ką darote, kaip dirbate, kuo išsiskiriate…"
                />
                <InputError :message="form.errors.description" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <Spinner v-if="form.processing" />
                Išsaugoti ir tęsti
            </Button>
        </form>
    </ProviderWizard>
</template>

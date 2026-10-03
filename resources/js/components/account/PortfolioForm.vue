<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ImagePlus, Trash2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { destroy as destroyImage } from '@/routes/portfolio/images';
import type {
    IdName,
    PortfolioImage,
    PortfolioItemData,
    RegionOption,
} from '@/types';

const props = defineProps<{
    /** null – naujas darbas */
    item: PortfolioItemData | null;
    categories: IdName[];
    regions: RegionOption[];
    maxImages: number;
    /** Kur siųsti formą (store arba update maršrutas) */
    action: string;
}>();

/** Turi sutapti su App\Concerns\ImageValidationRules (5 MB) */
const MAX_BYTES = 5 * 1024 * 1024;

const form = useForm({
    title: props.item?.title ?? '',
    description: props.item?.description ?? '',
    category_id: props.item?.category_id ?? null,
    city_id: props.item?.city_id ?? null,
    completed_date: props.item?.completed_date ?? '',
    images: [] as File[],
});

const fileInput = useTemplateRef<HTMLInputElement>('fileInput');
const previews = ref<string[]>([]);
const clientError = ref<string | null>(null);

const existingCount = computed(() => props.item?.images.length ?? 0);
const remaining = computed(
    () => props.maxImages - existingCount.value - form.images.length,
);

// Klaidos apie konkretų failą ateina kaip „images.0", „images.1"…
const imagesError = computed(
    () =>
        clientError.value ??
        form.errors.images ??
        Object.entries(form.errors).find(([key]) =>
            key.startsWith('images.'),
        )?.[1],
);

function addFiles(event: Event): void {
    clientError.value = null;
    const files = Array.from((event.target as HTMLInputElement).files ?? []);

    for (const file of files) {
        if (remaining.value <= 0) {
            clientError.value = `Viename darbe gali būti daugiausia ${props.maxImages} nuotraukų.`;
            break;
        }

        if (file.size > MAX_BYTES) {
            clientError.value = `„${file.name}“ per didelė – daugiausia 5 MB.`;
            continue;
        }

        form.images.push(file);
        // Laikinas naršyklės URL peržiūrai (failas dar neįkeltas į serverį)
        previews.value.push(URL.createObjectURL(file));
    }

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function removeNew(index: number): void {
    URL.revokeObjectURL(previews.value[index]);
    previews.value.splice(index, 1);
    form.images.splice(index, 1);
}

function clearNew(): void {
    previews.value.forEach((url) => URL.revokeObjectURL(url));
    previews.value = [];
    form.images = [];
}

function removeExisting(image: PortfolioImage): void {
    if (!props.item) {
        return;
    }

    router.delete(
        destroyImage({ portfolioItem: props.item.id, media: image.id }).url,
        { preserveScroll: true },
    );
}

function submit(): void {
    // Failų siuntimas: tik POST + multipart/form-data. Redaguojant PUT „imituojamas"
    // lauku _method=put – Laravel jį supranta (method spoofing).
    form.transform((data) =>
        props.item ? { ...data, _method: 'put' } : data,
    ).post(props.action, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => clearNew(),
    });
}

onBeforeUnmount(() =>
    previews.value.forEach((url) => URL.revokeObjectURL(url)),
);
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="title">Pavadinimas</Label>
            <Input
                id="title"
                v-model="form.title"
                required
                maxlength="150"
                placeholder="Vonios kambario renovacija"
            />
            <InputError :message="form.errors.title" />
        </div>

        <div class="grid gap-2">
            <Label for="description">Aprašymas</Label>
            <Textarea
                id="description"
                v-model="form.description"
                rows="4"
                maxlength="3000"
                placeholder="Kas buvo padaryta, kiek užtruko, kokios medžiagos…"
            />
            <InputError :message="form.errors.description" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="category_id">Kategorija</Label>
                <NativeSelect id="category_id" v-model="form.category_id">
                    <option :value="null">Be kategorijos</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </NativeSelect>
                <InputError :message="form.errors.category_id" />
            </div>
            <div class="grid gap-2">
                <Label for="city_id">Vieta</Label>
                <NativeSelect id="city_id" v-model="form.city_id">
                    <option :value="null">Nenurodyta</option>
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
            <div class="grid gap-2">
                <Label for="completed_date">Atlikta</Label>
                <Input
                    id="completed_date"
                    v-model="form.completed_date"
                    type="date"
                />
                <InputError :message="form.errors.completed_date" />
            </div>
        </div>

        <div class="space-y-3">
            <div>
                <p class="text-sm font-medium">Nuotraukos</p>
                <p class="text-xs text-muted-foreground">
                    JPG, PNG arba WEBP, iki 5 MB, daugiausia {{ maxImages }}.
                    Pirmoji nuotrauka bus darbo viršelis.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="image in item?.images ?? []"
                    :key="image.id"
                    class="group relative aspect-[4/3] overflow-hidden rounded-lg border"
                >
                    <img
                        :src="image.thumb"
                        alt=""
                        class="size-full object-cover"
                    />
                    <button
                        type="button"
                        class="absolute top-1 right-1 rounded-md bg-background/90 p-1.5 shadow-sm hover:bg-background"
                        aria-label="Pašalinti nuotrauką"
                        @click="removeExisting(image)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>

                <div
                    v-for="(preview, index) in previews"
                    :key="preview"
                    class="relative aspect-[4/3] overflow-hidden rounded-lg border border-dashed"
                >
                    <img :src="preview" alt="" class="size-full object-cover" />
                    <span
                        class="absolute bottom-1 left-1 rounded bg-background/90 px-1.5 text-xs"
                        >nauja</span
                    >
                    <button
                        type="button"
                        class="absolute top-1 right-1 rounded-md bg-background/90 p-1.5 shadow-sm hover:bg-background"
                        aria-label="Nebeįkelti šios nuotraukos"
                        @click="removeNew(index)"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <button
                    v-if="remaining > 0"
                    type="button"
                    class="flex aspect-[4/3] flex-col items-center justify-center gap-1 rounded-lg border border-dashed text-sm text-muted-foreground hover:bg-accent"
                    @click="fileInput?.click()"
                >
                    <ImagePlus class="size-6" />
                    Pridėti
                </button>
            </div>

            <input
                ref="fileInput"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="addFiles"
            />
            <InputError :message="imagesError" />
        </div>

        <Button type="submit" :disabled="form.processing">
            <Spinner v-if="form.processing" />
            {{ item ? 'Išsaugoti' : 'Pridėti darbą' }}
        </Button>
        <p v-if="form.progress" class="text-xs text-muted-foreground">
            Įkeliama: {{ form.progress.percentage }} %
        </p>
    </form>
</template>

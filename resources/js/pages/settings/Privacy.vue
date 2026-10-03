<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Download, FileArchive } from '@lucide/vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import { edit, exportMethod } from '@/routes/privacy';

type DataExport = {
    file: string;
    size: number;
    created_at: string;
    expires_at: string;
    download_url: string;
};

defineProps<{
    exports: DataExport[];
    retentionDays: number;
    canDelete: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Privatumas', href: edit() }],
    },
});

// 1536000 → „1,5 MB"
function formatSize(bytes: number): string {
    const megabytes = bytes / 1024 / 1024;

    return megabytes >= 1
        ? `${megabytes.toLocaleString('lt-LT', { maximumFractionDigits: 1 })} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
</script>

<template>
    <Head title="Privatumas" />

    <h1 class="sr-only">Privatumas</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Jūsų duomenys"
            description="Atsisiųskite visų apie jus saugomų duomenų kopiją (BDAR 15 ir 20 straipsniai)"
        />

        <div class="space-y-3 text-sm text-muted-foreground">
            <p>
                Archyve rasite paskyros duomenis, teikėjo profilį, užklausas,
                pasiūlymus, pokalbius, atsiliepimus, mokėjimus, pranešimus ir
                visas įkeltas nuotraukas. Duomenys pateikiami JSON formatu, kurį
                galima atidaryti ir perkelti į kitą paslaugą.
            </p>
            <p>
                Archyvą paruošime per kelias minutes ir apie tai pranešime el.
                paštu. Jis bus saugomas {{ retentionDays }} d.
            </p>
        </div>

        <Form v-bind="exportMethod.form()" v-slot="{ processing }">
            <Button
                type="submit"
                :disabled="processing"
                data-test="request-export-button"
            >
                <FileArchive class="size-4" />
                Paruošti duomenų archyvą
            </Button>
        </Form>

        <ul v-if="exports.length > 0" class="divide-y rounded-lg border">
            <li
                v-for="item in exports"
                :key="item.file"
                class="flex items-center justify-between gap-4 px-4 py-3 text-sm"
            >
                <div>
                    <p class="font-medium">
                        Archyvas, paruoštas {{ formatDate(item.created_at) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ formatSize(item.size) }} · galioja iki
                        {{ formatDate(item.expires_at) }}
                    </p>
                </div>
                <!-- Paprasta nuoroda (ne Inertia Link): atsisiunčiamas failas, o ne puslapis -->
                <Button variant="outline" size="sm" as-child>
                    <a :href="item.download_url" download>
                        <Download class="size-4" />
                        Atsisiųsti
                    </a>
                </Button>
            </li>
        </ul>
    </div>

    <div class="space-y-4">
        <Heading
            variant="small"
            title="Ką saugome ištrynus paskyrą"
            description="Ištrynimas negrąžinamas"
        />
        <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
            <li>
                Pašaliname vardą, pavardę, el. paštą, telefoną, nuotraukas,
                užklausų adresus, pranešimus ir teikėjo profilį.
            </li>
            <li>
                Atviros užklausos ir laukiantys pasiūlymai atšaukiami (teikėjams
                grąžinami kreditai pagal taisykles).
            </li>
            <li>
                Paliekame užklausas, pasiūlymus, žinutes ir atsiliepimus be jūsų
                vardo – juose rodoma „Ištrintas vartotojas". Tai kitų žmonių
                darbų istorija.
            </li>
            <li>
                Mokėjimus ir sąskaitas saugome 10 metų, kaip reikalauja
                buhalterinės apskaitos įstatymai.
            </li>
        </ul>
    </div>

    <DeleteUser v-if="canDelete" />
    <p v-else class="text-sm text-muted-foreground">
        Administratoriaus paskyros ištrinti negalima – kreipkitės į kitą
        administratorių.
    </p>
</template>

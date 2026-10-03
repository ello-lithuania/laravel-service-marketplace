<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Brush,
    CalendarHeart,
    ClipboardList,
    Hammer,
    Laptop,
    MessageSquareText,
    Plug,
    Scissors,
    Sparkles,
    Star,
    Truck,
    Wrench,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { register } from '@/routes';

// Pradžios puslapio griaučiai. Kategorijos laikinai įrašytos čia –
// Etape 4 jas imsim iš duomenų bazės (categories lentelės).
const categories = [
    { name: 'Statyba ir remontas', icon: Hammer },
    { name: 'Santechnika', icon: Wrench },
    { name: 'Elektros darbai', icon: Plug },
    { name: 'Valymo paslaugos', icon: Sparkles },
    { name: 'Perkraustymas', icon: Truck },
    { name: 'Grožis ir sveikata', icon: Scissors },
    { name: 'IT paslaugos', icon: Laptop },
    { name: 'Renginiai', icon: CalendarHeart },
];

const steps = [
    {
        icon: ClipboardList,
        title: 'Aprašykite darbą',
        text: 'Nurodykite, ko reikia, kur ir kada. Tai nemokama ir užtrunka porą minučių.',
    },
    {
        icon: MessageSquareText,
        title: 'Gaukite pasiūlymus',
        text: 'Jūsų mieste dirbantys teikėjai atsiųs kainas ir terminus.',
    },
    {
        icon: Star,
        title: 'Išsirinkite ir įvertinkite',
        text: 'Palyginkite atsiliepimus, pasirinkite geriausią ir po darbo palikite įvertinimą.',
    },
];
</script>

<template>
    <Head title="Paslaugos ir meistrai visoje Lietuvoje" />

    <section class="border-b bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-16 md:py-24">
            <h1
                class="max-w-2xl text-3xl font-semibold tracking-tight md:text-5xl"
            >
                Raskite patikimą meistrą per kelias minutes
            </h1>
            <p class="mt-4 max-w-xl text-lg text-muted-foreground">
                Aprašykite darbą, gaukite pasiūlymus iš teikėjų ir išsirinkite
                geriausią – nuo santechniko iki renginių organizatoriaus.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <Button size="lg" as-child>
                    <Link :href="register()">Sukurti užklausą</Link>
                </Button>
                <Button size="lg" variant="outline" as-child>
                    <a href="#teikejams">Esu paslaugų teikėjas</a>
                </Button>
            </div>
        </div>
    </section>

    <section id="kategorijos" class="mx-auto max-w-6xl px-4 py-16">
        <h2 class="text-2xl font-semibold tracking-tight">
            Populiarios kategorijos
        </h2>
        <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <a
                v-for="category in categories"
                :key="category.name"
                href="#"
                class="flex flex-col items-start gap-3 rounded-lg border p-4 transition-colors hover:bg-accent"
            >
                <component
                    :is="category.icon"
                    class="size-6 text-primary"
                    aria-hidden="true"
                />
                <span class="font-medium">{{ category.name }}</span>
            </a>
        </div>
    </section>

    <section id="kaip-tai-veikia" class="border-y bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-16">
            <h2 class="text-2xl font-semibold tracking-tight">
                Kaip tai veikia
            </h2>
            <ol class="mt-8 grid gap-8 md:grid-cols-3">
                <li v-for="(step, index) in steps" :key="step.title">
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-10 items-center justify-center rounded-full bg-primary text-primary-foreground"
                        >
                            <component
                                :is="step.icon"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="text-sm text-muted-foreground"
                            >{{ index + 1 }} žingsnis</span
                        >
                    </div>
                    <h3 class="mt-4 font-semibold">{{ step.title }}</h3>
                    <p class="mt-1 text-muted-foreground">{{ step.text }}</p>
                </li>
            </ol>
        </div>
    </section>

    <section id="teikejams" class="mx-auto max-w-6xl px-4 py-16">
        <div
            class="flex flex-col items-start justify-between gap-6 rounded-xl border p-8 md:flex-row md:items-center"
        >
            <div class="flex items-start gap-4">
                <Brush
                    class="mt-1 size-8 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div>
                    <h2 class="text-xl font-semibold">
                        Teikiate paslaugas? Gaukite naujų klientų
                    </h2>
                    <p class="mt-1 text-muted-foreground">
                        Sukurkite profilį, pasirinkite kategorijas ir miestus –
                        naujas užklausas gausite el. paštu.
                    </p>
                </div>
            </div>
            <Button size="lg" as-child>
                <Link :href="register()">Tapti teikėju</Link>
            </Button>
        </div>
    </section>
</template>

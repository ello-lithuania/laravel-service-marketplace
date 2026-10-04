<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Check,
    Coins,
    Crown,
    FileText,
    Gift,
    RotateCcw,
    Send,
} from '@lucide/vue';
import { computed } from 'vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import FaqList from '@/components/site/FaqList.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import SectionHeading from '@/components/site/SectionHeading.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { usePurchase } from '@/composables/usePurchase';
import { formatCredits } from '@/lib/billing';
import { brandTones } from '@/lib/brandTones';
import { formatPrice, plural } from '@/lib/format';
import { register } from '@/routes';
import { wizard } from '@/routes/provider';
import type {
    CreditPackage,
    SeoMeta,
    SitePhotoData,
    SubscriptionPlan,
} from '@/types';

// Kainų puslapis (PricingController): paketai ir planai iš DB, mygtukai – pagal tai, kas žiūri.
const props = defineProps<{
    packages: CreditPackage[];
    plans: SubscriptionPlan[];
    offerCost: { min: number; max: number };
    // Etapas 9c: kategorijų riba be prenumeratos (config marketplace.free_max_categories)
    freeMaxCategories: number;
    viewer: {
        role: 'client' | 'provider' | 'admin' | null;
        can_purchase: boolean;
        current_plan_id: number | null;
    };
    seo: SeoMeta;
    // Etapas 10: kvietimo teikėjams nuotrauka (null – atsarginis dizainas)
    photos: { providers: SitePhotoData };
}>();

const { processing, error, buyPackage, buyPlan } = usePurchase();

const offerCostText = computed(() =>
    props.offerCost.min === props.offerCost.max
        ? formatCredits(props.offerCost.min)
        : `${props.offerCost.min}–${props.offerCost.max} kreditai`,
);

// Vidurinis planas pažymimas kaip populiariausias (kai planų bent trys)
const highlightedPlanId = computed(() =>
    props.plans.length >= 3 ? props.plans[1].id : null,
);

// Etapas 10: pigiausio kredito paketas pažymimas „Geriausia kaina"
const bestValuePackageId = computed(() => {
    const priced = props.packages.filter(
        (pkg) => pkg.price_per_credit_cents !== null,
    );

    if (priced.length < 2) {
        return null;
    }

    return priced.reduce((best, pkg) =>
        (pkg.price_per_credit_cents ?? Infinity) <
        (best.price_per_credit_cents ?? Infinity)
            ? pkg
            : best,
    ).id;
});

const registerAsProvider = register({ query: { role: 'provider' } });

// Pranešimas, kodėl pirkti negalima (kliento ar administratoriaus paskyra, neužpildytas teikėjo profilis)
const hasNotice = computed(
    () =>
        props.viewer.role === 'client' ||
        props.viewer.role === 'admin' ||
        (props.viewer.role === 'provider' && !props.viewer.can_purchase),
);

const facts = computed(() => [
    {
        icon: Send,
        title: `Pasiūlymas – ${offerCostText.value}`,
        text: 'Kaina priklauso nuo paslaugos. Klientams platforma nemokama.',
    },
    {
        icon: RotateCcw,
        title: 'Kreditai grąžinami',
        text: 'Jei klientas atšaukia užklausą arba jūsų pasiūlymo taip ir neatidaro.',
    },
    {
        icon: FileText,
        title: 'Sąskaita faktūra',
        text: 'Po kiekvieno apmokėjimo – PDF sąskaita jūsų paskyroje.',
    },
]);

const faq = [
    {
        q: 'Kaip apmokėti?',
        a: 'Per Paysera: Lietuvos bankų elektroninę bankininkystę arba mokėjimo kortele. Kreditai užskaitomi iškart, kai bankas patvirtina mokėjimą.',
    },
    {
        q: 'Ar kreditai turi galiojimo laiką?',
        a: 'Ne. Nupirkti ir prenumeratos kreditai lieka jūsų sąskaitoje, kol juos panaudosite.',
    },
    {
        q: 'Kaip veikia prenumerata?',
        a: 'Kas mėnesį gaunate plano kreditų. Likus 7 dienoms iki laikotarpio pabaigos atsiunčiame nuorodą apmokėti kitą mėnesį. Atšaukti galite bet kada – prenumerata galios iki apmokėto laikotarpio pabaigos.',
    },
    {
        q: 'Ar galiu pakeisti planą?',
        a: 'Taip. Naujas planas prasidės, kai baigsis dabartinis apmokėtas laikotarpis.',
    },
];
</script>

<template>
    <SeoHead :seo="seo" />

    <!-- Viršus: antraštė ir nuotrauka -->
    <section
        class="border-b bg-gradient-to-b from-secondary/70 to-background dark:from-secondary/30"
        aria-labelledby="pricing-title"
    >
        <div
            class="page-container grid items-center gap-10 py-12 md:py-16 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16"
        >
            <div>
                <p
                    class="inline-flex items-center gap-2 text-xs font-semibold tracking-[0.14em] text-primary uppercase"
                >
                    <span class="h-px w-6 bg-primary" aria-hidden="true" />
                    Teikėjams
                </p>
                <h1
                    id="pricing-title"
                    class="mt-3 text-4xl leading-[1.05] font-bold text-balance md:text-5xl lg:text-[3.4rem]"
                >
                    Mokate tik už išsiųstus pasiūlymus
                </h1>
                <p
                    class="mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground"
                >
                    Klientams platforma nemokama. Teikėjai moka tik už išsiųstus
                    pasiūlymus – kreditais, kuriuos galima pirkti paketais arba
                    gauti kas mėnesį su prenumerata.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Button variant="cta" size="xl" as-child>
                        <a href="#kreditai">Kreditų paketai</a>
                    </Button>
                    <Button variant="outline" size="xl" as-child>
                        <a href="#planai">Prenumeratos</a>
                    </Button>
                </div>
            </div>

            <div class="relative">
                <div
                    class="relative aspect-[4/3] overflow-hidden rounded-3xl shadow-lift"
                >
                    <PhotoSlot
                        :src="photos.providers?.url"
                        :alt="
                            photos.providers?.alt ??
                            'Paslaugų teikėjas savo darbo vietoje'
                        "
                        :width="1600"
                        :height="1200"
                        eager
                        sizes="(min-width: 1024px) 40vw, 100vw"
                    >
                        <template #fallback>
                            <!-- Be nuotraukos: kreditų sąskaitos iliustracija -->
                            <div
                                class="absolute inset-0 flex items-center justify-center p-6"
                                :style="{
                                    backgroundImage: `linear-gradient(135deg, ${brandTones.pine.from}, ${brandTones.teal.to})`,
                                }"
                                aria-hidden="true"
                            >
                                <div
                                    class="absolute inset-0 pattern-dots text-white/[0.08]"
                                />
                                <div
                                    class="relative w-full max-w-xs rounded-2xl bg-card p-5 text-card-foreground shadow-float"
                                >
                                    <p
                                        class="text-xs font-semibold text-muted-foreground"
                                    >
                                        Kreditų likutis
                                    </p>
                                    <p
                                        class="mt-1 flex items-center gap-2 font-display text-4xl font-semibold"
                                    >
                                        <Coins class="size-8 text-cta" />
                                        48
                                    </p>
                                    <div class="mt-4 space-y-2 text-sm">
                                        <p class="flex justify-between">
                                            <span class="text-muted-foreground"
                                                >Pasiūlymas: Laminato
                                                klojimas</span
                                            >
                                            <span class="font-medium">−3</span>
                                        </p>
                                        <p class="flex justify-between">
                                            <span class="text-muted-foreground"
                                                >Grąžinta: užklausa
                                                atšaukta</span
                                            >
                                            <span
                                                class="font-medium text-primary"
                                                >+2</span
                                            >
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </PhotoSlot>
                </div>
            </div>
        </div>
    </section>

    <div class="page-container space-y-20 py-14 md:py-16">
        <ul class="grid gap-4 md:grid-cols-3">
            <li
                v-for="fact in facts"
                :key="fact.title"
                class="flex gap-4 rounded-2xl border bg-card p-5 shadow-soft"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary"
                >
                    <component
                        :is="fact.icon"
                        class="size-5"
                        aria-hidden="true"
                    />
                </span>
                <div>
                    <p class="font-semibold">{{ fact.title }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ fact.text }}
                    </p>
                </div>
            </li>
        </ul>

        <div v-if="error || hasNotice" class="space-y-4">
            <Alert v-if="error" variant="destructive">
                <AlertDescription>{{ error }}</AlertDescription>
            </Alert>

            <div
                v-if="viewer.role === 'client' || viewer.role === 'admin'"
                class="rounded-2xl border border-dashed bg-card/60 p-4 text-sm text-muted-foreground"
            >
                Kreditus ir prenumeratas perka paslaugų teikėjai. Jūsų paskyra –
                {{
                    viewer.role === 'client' ? 'kliento' : 'administratoriaus'
                }}, todėl pirkti negalite.
            </div>
            <div
                v-else-if="viewer.role === 'provider' && !viewer.can_purchase"
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                Pirmiausia užpildykite teikėjo profilį – tada galėsite pirkti
                kreditų.
                <Link :href="wizard()" class="font-medium underline">
                    Pildyti profilį
                </Link>
            </div>
        </div>

        <section
            id="kreditai"
            class="scroll-mt-24"
            aria-labelledby="packages-title"
        >
            <SectionHeading
                id="packages-title"
                eyebrow="Vienkartinis pirkimas"
                title="Kreditų paketai"
                description="Kuo didesnis paketas, tuo pigesnis kreditas. Nupirkti kreditai nenustoja galioti."
            />

            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="relative flex flex-col rounded-2xl border bg-card p-6 shadow-soft transition-shadow hover:shadow-lift"
                    :class="
                        pkg.id === bestValuePackageId
                            ? 'border-cta/60 ring-1 ring-cta/40'
                            : ''
                    "
                    data-test="credit-package"
                >
                    <span
                        v-if="pkg.id === bestValuePackageId"
                        class="absolute -top-3 left-6 rounded-full bg-cta px-3 py-0.5 text-xs font-semibold text-cta-foreground"
                    >
                        Geriausia kaina
                    </span>
                    <p class="font-semibold">{{ pkg.name }}</p>
                    <p class="mt-4 flex items-baseline gap-2">
                        <span
                            class="font-display text-5xl font-semibold tracking-tight numeric"
                            >{{ pkg.total_credits }}</span
                        >
                        <span class="text-muted-foreground">kreditų</span>
                    </p>
                    <p
                        v-if="pkg.bonus_credits > 0"
                        class="mt-2 inline-flex w-fit items-center gap-1 rounded-full bg-secondary px-2.5 py-0.5 text-xs font-semibold text-secondary-foreground"
                    >
                        <Gift class="size-3.5" aria-hidden="true" />
                        +{{ pkg.bonus_credits }} dovanų
                    </p>
                    <div class="mt-6 border-t pt-4">
                        <p class="text-2xl font-semibold numeric">
                            {{ formatPrice(pkg.price_cents) }}
                        </p>
                        <p
                            v-if="pkg.price_per_credit_cents !== null"
                            class="text-sm text-muted-foreground"
                        >
                            ≈ {{ formatPrice(pkg.price_per_credit_cents) }} už
                            kreditą
                        </p>
                    </div>
                    <div class="mt-auto pt-6">
                        <Button
                            v-if="viewer.can_purchase"
                            class="w-full"
                            :variant="
                                pkg.id === bestValuePackageId
                                    ? 'cta'
                                    : 'default'
                            "
                            :disabled="processing !== null"
                            @click="buyPackage(pkg.id)"
                        >
                            {{
                                processing === `package-${pkg.id}`
                                    ? 'Nukreipiama…'
                                    : 'Pirkti'
                            }}
                        </Button>
                        <Button
                            v-else-if="viewer.role === null"
                            variant="outline"
                            class="w-full"
                            as-child
                        >
                            <Link :href="registerAsProvider"
                                >Registruotis kaip teikėjas</Link
                            >
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <section id="planai" class="scroll-mt-24" aria-labelledby="plans-title">
            <SectionHeading
                id="plans-title"
                eyebrow="Kas mėnesį"
                title="Prenumeratos"
                description="Kreditai kas mėnesį ir papildomi privalumai. Galima atšaukti bet kada."
            />
            <p
                class="mt-3 inline-flex items-center gap-2 text-sm text-muted-foreground"
                data-test="free-max-categories"
            >
                <BadgeCheck class="size-4 text-primary" aria-hidden="true" />
                Be prenumeratos galite pasirinkti iki
                {{
                    plural(freeMaxCategories, [
                        'paslaugų kategorijos',
                        'paslaugų kategorijų',
                        'paslaugų kategorijų',
                    ])
                }}.
            </p>

            <div class="mt-10 grid items-stretch gap-4 md:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="relative flex flex-col rounded-3xl border p-7 shadow-soft"
                    :class="
                        plan.id === highlightedPlanId
                            ? 'border-transparent bg-brand-deep text-brand-deep-foreground shadow-lift md:-my-4 md:py-11'
                            : 'bg-card'
                    "
                    data-test="subscription-plan"
                >
                    <span
                        v-if="plan.id === highlightedPlanId"
                        class="absolute -top-3 left-7 inline-flex items-center gap-1 rounded-full bg-cta px-3 py-0.5 text-xs font-semibold text-cta-foreground"
                    >
                        <Crown class="size-3.5" aria-hidden="true" />
                        Populiariausias
                    </span>
                    <p
                        class="font-display text-xl font-semibold"
                        :class="
                            plan.id === highlightedPlanId ? 'text-white' : ''
                        "
                    >
                        {{ plan.name }}
                    </p>
                    <p
                        v-if="plan.description"
                        class="mt-1 text-sm"
                        :class="
                            plan.id === highlightedPlanId
                                ? 'text-brand-deep-muted'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ plan.description }}
                    </p>
                    <p class="mt-6">
                        <span
                            class="font-display text-5xl font-semibold tracking-tight numeric"
                            :class="
                                plan.id === highlightedPlanId
                                    ? 'text-white'
                                    : ''
                            "
                            >{{ formatPrice(plan.price_cents) }}</span
                        >
                        <span
                            :class="
                                plan.id === highlightedPlanId
                                    ? 'text-brand-deep-muted'
                                    : 'text-muted-foreground'
                            "
                        >
                            / {{ plan.billing_period.per }}</span
                        >
                    </p>
                    <p
                        v-if="plan.price_per_credit_cents !== null"
                        class="mt-1 text-sm"
                        :class="
                            plan.id === highlightedPlanId
                                ? 'text-brand-deep-muted'
                                : 'text-muted-foreground'
                        "
                    >
                        ≈ {{ formatPrice(plan.price_per_credit_cents) }} už
                        kreditą
                    </p>
                    <ul
                        class="mt-6 space-y-2.5 border-t pt-6 text-sm"
                        :class="
                            plan.id === highlightedPlanId
                                ? 'border-white/10'
                                : ''
                        "
                    >
                        <li
                            v-for="feature in plan.features"
                            :key="feature"
                            class="flex items-start gap-2.5"
                        >
                            <span
                                class="mt-0.5 flex size-4.5 shrink-0 items-center justify-center rounded-full"
                                :class="
                                    plan.id === highlightedPlanId
                                        ? 'bg-cta text-cta-foreground'
                                        : 'bg-secondary text-primary'
                                "
                            >
                                <Check class="size-3" aria-hidden="true" />
                            </span>
                            {{ feature }}
                        </li>
                    </ul>
                    <div class="mt-auto pt-8">
                        <p
                            v-if="viewer.current_plan_id === plan.id"
                            class="rounded-lg px-3 py-2.5 text-center text-sm font-medium"
                            :class="
                                plan.id === highlightedPlanId
                                    ? 'bg-white/10'
                                    : 'bg-muted'
                            "
                        >
                            Jūsų dabartinis planas
                        </p>
                        <Button
                            v-else-if="viewer.can_purchase"
                            class="w-full"
                            size="lg"
                            :variant="
                                plan.id === highlightedPlanId
                                    ? 'cta'
                                    : 'outline'
                            "
                            :disabled="processing !== null"
                            @click="buyPlan(plan.slug)"
                        >
                            {{
                                processing === `plan-${plan.slug}`
                                    ? 'Nukreipiama…'
                                    : 'Prenumeruoti'
                            }}
                        </Button>
                        <Button
                            v-else-if="viewer.role === null"
                            :variant="
                                plan.id === highlightedPlanId
                                    ? 'cta'
                                    : 'outline'
                            "
                            size="lg"
                            class="w-full"
                            as-child
                        >
                            <Link :href="registerAsProvider"
                                >Registruotis kaip teikėjas</Link
                            >
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16"
            aria-labelledby="faq-title"
        >
            <SectionHeading
                id="faq-title"
                class="self-start"
                eyebrow="DUK"
                title="Dažni klausimai"
                description="Apie mokėjimus, kreditus ir prenumeratas."
            />
            <FaqList :items="faq" />
        </section>
    </div>
</template>

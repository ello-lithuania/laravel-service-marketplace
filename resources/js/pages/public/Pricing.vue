<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Check,
    Coins,
    FileText,
    RotateCcw,
    Send,
} from '@lucide/vue';
import { computed } from 'vue';
import SeoHead from '@/components/catalog/SeoHead.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { usePurchase } from '@/composables/usePurchase';
import { formatCredits } from '@/lib/billing';
import { formatPrice, plural } from '@/lib/format';
import { register } from '@/routes';
import { wizard } from '@/routes/provider';
import type { CreditPackage, SeoMeta, SubscriptionPlan } from '@/types';

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

const registerAsProvider = register({ query: { role: 'provider' } });

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

    <section class="border-b bg-muted/40">
        <div class="mx-auto max-w-6xl px-4 py-14 text-center">
            <h1 class="text-3xl font-semibold tracking-tight md:text-4xl">
                Kainos teikėjams
            </h1>
            <p class="mx-auto mt-3 max-w-2xl text-lg text-muted-foreground">
                Klientams platforma nemokama. Teikėjai moka tik už išsiųstus
                pasiūlymus – kreditais, kuriuos galima pirkti paketais arba
                gauti kas mėnesį su prenumerata.
            </p>
        </div>
    </section>

    <div class="mx-auto max-w-6xl space-y-16 px-4 py-12">
        <ul class="grid gap-4 md:grid-cols-3">
            <li
                v-for="fact in facts"
                :key="fact.title"
                class="flex gap-3 rounded-xl border p-4"
            >
                <component
                    :is="fact.icon"
                    class="mt-0.5 size-5 shrink-0 text-primary"
                    aria-hidden="true"
                />
                <div>
                    <p class="font-medium">{{ fact.title }}</p>
                    <p class="text-sm text-muted-foreground">{{ fact.text }}</p>
                </div>
            </li>
        </ul>

        <Alert v-if="error" variant="destructive">
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <div
            v-if="viewer.role === 'client' || viewer.role === 'admin'"
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            Kreditus ir prenumeratas perka paslaugų teikėjai. Jūsų paskyra –
            {{ viewer.role === 'client' ? 'kliento' : 'administratoriaus' }},
            todėl pirkti negalite.
        </div>
        <div
            v-else-if="viewer.role === 'provider' && !viewer.can_purchase"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            Pirmiausia užpildykite teikėjo profilį – tada galėsite pirkti
            kreditų.
            <Link :href="wizard()" class="font-medium underline">
                Pildyti profilį
            </Link>
        </div>

        <section id="kreditai" aria-labelledby="packages-title">
            <div class="flex items-center gap-2">
                <Coins class="size-6 text-amber-500" aria-hidden="true" />
                <h2 id="packages-title" class="text-2xl font-semibold">
                    Kreditų paketai
                </h2>
            </div>
            <p class="mt-1 text-muted-foreground">
                Vienkartinis pirkimas. Kuo didesnis paketas, tuo pigesnis
                kreditas.
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="pkg in packages"
                    :key="pkg.id"
                    class="flex flex-col rounded-xl border p-5"
                    data-test="credit-package"
                >
                    <p class="font-medium">{{ pkg.name }}</p>
                    <p class="mt-3 text-4xl font-semibold">
                        {{ pkg.total_credits }}
                        <span
                            class="text-base font-normal text-muted-foreground"
                            >kred.</span
                        >
                    </p>
                    <p
                        v-if="pkg.bonus_credits > 0"
                        class="mt-1 inline-flex w-fit rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300"
                    >
                        +{{ pkg.bonus_credits }} dovanų
                    </p>
                    <p class="mt-4 text-2xl font-semibold">
                        {{ formatPrice(pkg.price_cents) }}
                    </p>
                    <p
                        v-if="pkg.price_per_credit_cents !== null"
                        class="text-sm text-muted-foreground"
                    >
                        ≈ {{ formatPrice(pkg.price_per_credit_cents) }} už
                        kreditą
                    </p>
                    <div class="mt-auto pt-5">
                        <Button
                            v-if="viewer.can_purchase"
                            class="w-full"
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

        <section id="planai" aria-labelledby="plans-title">
            <div class="flex items-center gap-2">
                <BadgeCheck class="size-6 text-primary" aria-hidden="true" />
                <h2 id="plans-title" class="text-2xl font-semibold">
                    Prenumeratos
                </h2>
            </div>
            <p class="mt-1 text-muted-foreground">
                Kreditai kas mėnesį ir papildomi privalumai. Galima atšaukti bet
                kada.
            </p>
            <p
                class="mt-1 text-sm text-muted-foreground"
                data-test="free-max-categories"
            >
                Be prenumeratos galite pasirinkti iki
                {{
                    plural(freeMaxCategories, [
                        'paslaugų kategorijos',
                        'paslaugų kategorijų',
                        'paslaugų kategorijų',
                    ])
                }}.
            </p>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="relative flex flex-col rounded-xl border p-6"
                    :class="
                        plan.id === highlightedPlanId
                            ? 'border-primary shadow-sm ring-1 ring-primary'
                            : ''
                    "
                    data-test="subscription-plan"
                >
                    <span
                        v-if="plan.id === highlightedPlanId"
                        class="absolute -top-3 left-6 rounded-full bg-primary px-3 py-0.5 text-xs font-medium text-primary-foreground"
                    >
                        Populiariausias
                    </span>
                    <p class="text-lg font-semibold">{{ plan.name }}</p>
                    <p
                        v-if="plan.description"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        {{ plan.description }}
                    </p>
                    <p class="mt-4">
                        <span class="text-3xl font-semibold">{{
                            formatPrice(plan.price_cents)
                        }}</span>
                        <span class="text-muted-foreground">
                            / {{ plan.billing_period.per }}</span
                        >
                    </p>
                    <p
                        v-if="plan.price_per_credit_cents !== null"
                        class="text-sm text-muted-foreground"
                    >
                        ≈ {{ formatPrice(plan.price_per_credit_cents) }} už
                        kreditą
                    </p>
                    <ul class="mt-5 space-y-2 text-sm">
                        <li
                            v-for="feature in plan.features"
                            :key="feature"
                            class="flex items-start gap-2"
                        >
                            <Check
                                class="mt-0.5 size-4 shrink-0 text-emerald-600"
                                aria-hidden="true"
                            />
                            {{ feature }}
                        </li>
                    </ul>
                    <div class="mt-auto pt-6">
                        <p
                            v-if="viewer.current_plan_id === plan.id"
                            class="rounded-md bg-muted px-3 py-2 text-center text-sm font-medium"
                        >
                            Jūsų dabartinis planas
                        </p>
                        <Button
                            v-else-if="viewer.can_purchase"
                            class="w-full"
                            :variant="
                                plan.id === highlightedPlanId
                                    ? 'default'
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

        <section aria-labelledby="faq-title" class="max-w-3xl">
            <h2 id="faq-title" class="text-2xl font-semibold">
                Dažni klausimai
            </h2>
            <dl class="mt-6 space-y-5">
                <div v-for="item in faq" :key="item.q">
                    <dt class="font-medium">{{ item.q }}</dt>
                    <dd class="mt-1 text-muted-foreground">{{ item.a }}</dd>
                </div>
            </dl>
        </section>
    </div>
</template>

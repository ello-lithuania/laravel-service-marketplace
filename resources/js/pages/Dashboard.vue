<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleDashed,
    ClipboardPlus,
    Coins,
    Images,
    ShieldCheck,
    Star,
    UserRoundPen,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as creditsIndex } from '@/routes/credits';
import { show as showRequest } from '@/routes/service-requests';
import type { ChecklistItem, ProviderStatus, ReviewPrompt } from '@/types';

// „Mano paskyra": kiekviena rolė gauna savo skydelio duomenis (DashboardController),
// o kitos rolės blokas ateina null.
const props = defineProps<{
    client: {
        service_requests_count: number;
        review_prompts: ReviewPrompt[];
    } | null;
    provider: {
        display_name: string | null;
        status: ProviderStatus | null;
        status_label: string | null;
        credits_balance: number;
        portfolio_count: number;
        checklist: { percent: number; items: ChecklistItem[] };
        wizard_url: string;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mano paskyra',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const statusVariant = computed(() => {
    switch (props.provider?.status) {
        case 'active':
            return 'default';
        case 'suspended':
            return 'destructive';
        default:
            return 'secondary';
    }
});
</script>

<template>
    <Head title="Mano paskyra" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Sveiki, {{ user.first_name }}!
            </h1>
            <p class="text-muted-foreground">
                {{ page.props.name }} – jūsų paskyra
            </p>
        </div>

        <!-- Klientas -->
        <div v-if="client" class="grid gap-4 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <ClipboardPlus class="size-5 text-primary" />
                        Reikia meistro?
                    </CardTitle>
                    <CardDescription>
                        Aprašykite darbą – tinkami teikėjai atsiųs pasiūlymus.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <!-- Paprasta nuoroda: užklausos kūrimo puslapį kuria Etapas 5 -->
                    <Button as-child>
                        <a href="/uzklausos/nauja">Sukurti užklausą</a>
                    </Button>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle>Mano užklausos</CardTitle>
                    <CardDescription>
                        Iš viso sukurta: {{ client.service_requests_count }}
                    </CardDescription>
                </CardHeader>
            </Card>

            <!-- Etapas 6: atlikti darbai, kuriuos dar galima įvertinti -->
            <Card
                v-if="client.review_prompts.length"
                class="md:col-span-2"
                data-test="review-prompts"
            >
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Star class="size-5 fill-amber-400 text-amber-400" />
                        Įvertinkite atliktus darbus
                    </CardTitle>
                    <CardDescription>
                        Jūsų atsiliepimas padeda kitiems išsirinkti patikimą
                        meistrą.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="prompt in client.review_prompts"
                            :key="prompt.slug"
                            class="flex flex-wrap items-center justify-between gap-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium">
                                    {{ prompt.title }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ prompt.provider }}
                                </p>
                            </div>
                            <Button size="sm" variant="outline" as-child>
                                <Link
                                    :href="`${showRequest(prompt.slug).url}#atsiliepimas`"
                                >
                                    Palikti atsiliepimą
                                </Link>
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <!-- Teikėjas -->
        <div v-if="provider" class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle class="flex flex-wrap items-center gap-2">
                        <UserRoundPen class="size-5 text-primary" />
                        {{ provider.display_name ?? 'Teikėjo profilis' }}
                        <Badge
                            v-if="provider.status_label"
                            :variant="statusVariant"
                        >
                            {{ provider.status_label }}
                        </Badge>
                    </CardTitle>
                    <CardDescription v-if="!provider.status">
                        Užpildykite profilį – tada klientai jus ras ir galėsite
                        gauti užklausas.
                    </CardDescription>
                    <CardDescription v-else-if="provider.status === 'pending'">
                        Profilis dar nebaigtas: užpildykite privalomus žingsnius
                        ir jis taps matomas klientams.
                    </CardDescription>
                    <CardDescription v-else-if="provider.status === 'active'">
                        Profilis aktyvus ir matomas klientams.
                    </CardDescription>
                    <CardDescription v-else>
                        Profilis šiuo metu nerodomas klientams. Jei manote, kad
                        tai klaida, susisiekite su administracija.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div>
                        <div class="mb-1 flex justify-between text-sm">
                            <span>Profilio pilnumas</span>
                            <span class="font-medium"
                                >{{ provider.checklist.percent }} %</span
                            >
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="provider.checklist.percent"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full bg-primary transition-all"
                                :style="{
                                    width: `${provider.checklist.percent}%`,
                                }"
                            />
                        </div>
                    </div>

                    <ul class="grid gap-2 text-sm sm:grid-cols-2">
                        <li
                            v-for="item in provider.checklist.items"
                            :key="item.label"
                            class="flex items-center gap-2"
                        >
                            <CircleCheck
                                v-if="item.done"
                                class="size-4 text-green-600"
                            />
                            <CircleDashed
                                v-else
                                class="size-4 text-muted-foreground"
                            />
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                class="hover:underline"
                                >{{ item.label }}</Link
                            >
                            <span v-else>{{ item.label }}</span>
                            <span
                                v-if="!item.required"
                                class="text-xs text-muted-foreground"
                                >(neprivaloma)</span
                            >
                        </li>
                    </ul>

                    <Button as-child>
                        <Link :href="provider.wizard_url">
                            {{
                                provider.status === 'pending' ||
                                !provider.status
                                    ? 'Tęsti profilio pildymą'
                                    : 'Redaguoti profilį'
                            }}
                        </Link>
                    </Button>
                </CardContent>
            </Card>

            <div class="grid content-start gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Coins class="size-5 text-primary" />
                            Kreditai
                        </CardTitle>
                        <CardDescription>
                            Už kreditus siunčiami pasiūlymai klientams.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p class="text-3xl font-semibold">
                            {{ provider.credits_balance }}
                        </p>
                        <!-- Etapas 7: kreditų pirkimas ir istorija (reikia profilio) -->
                        <Button
                            v-if="provider.status"
                            size="sm"
                            variant="outline"
                            as-child
                        >
                            <Link :href="creditsIndex()">Pirkti kreditų</Link>
                        </Button>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Images class="size-5 text-primary" />
                            Atlikti darbai
                        </CardTitle>
                        <CardDescription>
                            Darbų su nuotraukomis:
                            {{ provider.portfolio_count }}
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </div>

        <!-- Administratorius -->
        <Card v-if="user.role === 'admin'" class="max-w-xl">
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <ShieldCheck class="size-5 text-primary" />
                    Administravimas
                </CardTitle>
                <CardDescription>
                    Užklausų, atsiliepimų ir skundų moderavimas, kategorijos,
                    mokėjimai.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- /admin – Filament (Livewire), todėl paprasta nuoroda, ne Inertia Link -->
                <Button as-child>
                    <a href="/admin">Atidaryti administravimo panelę</a>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>

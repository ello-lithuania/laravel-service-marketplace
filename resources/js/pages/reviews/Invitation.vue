<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleAlert, Star } from '@lucide/vue';
import ReviewForm from '@/components/reviews/ReviewForm.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import { formatDate } from '@/lib/marketplace';
import { show as showProvider } from '@/routes/providers';

defineProps<{
    provider: {
        display_name: string;
        slug: string;
        headline: string | null;
        logo_url: string | null;
        rating_avg: number;
        reviews_count: number;
    };
    can: { create: boolean; reason: string | null };
    expiresAt: string | null;
}>();

/*
 * Forma siunčiama į tą patį pasirašytą URL (su ?expires=…&signature=…): „signed" middleware tikrina ir POST,
 * todėl be parašo (ar pasibaigus terminui) atsiliepimo išsiųsti nepavyks.
 */
const page = usePage();
</script>

<template>
    <Head :title="`Atsiliepimas: ${provider.display_name}`" />

    <div class="mx-auto w-full max-w-2xl space-y-6 p-4 md:p-6">
        <header class="flex items-center gap-4 rounded-xl border bg-card p-4">
            <Avatar class="size-16 shrink-0 rounded-lg">
                <AvatarImage
                    v-if="provider.logo_url"
                    :src="provider.logo_url"
                    :alt="provider.display_name"
                />
                <AvatarFallback
                    class="rounded-lg bg-primary/10 text-lg font-semibold text-primary"
                >
                    {{ getInitials(provider.display_name) }}
                </AvatarFallback>
            </Avatar>
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">
                    Jus pakvietė įvertinti
                </p>
                <h1 class="text-xl font-semibold">
                    <Link
                        :href="showProvider(provider.slug)"
                        class="hover:underline"
                    >
                        {{ provider.display_name }}
                    </Link>
                </h1>
                <p
                    v-if="provider.headline"
                    class="text-sm text-muted-foreground"
                >
                    {{ provider.headline }}
                </p>
                <p
                    v-if="provider.reviews_count > 0"
                    class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Star class="size-3 fill-amber-400 text-amber-400" />
                    {{ provider.rating_avg.toFixed(1) }} ·
                    {{ provider.reviews_count }} atsil.
                </p>
            </div>
        </header>

        <section
            v-if="can.create"
            class="space-y-4 rounded-xl border bg-card p-4 md:p-6"
        >
            <div class="space-y-1">
                <h2 class="text-lg font-semibold">Jūsų atsiliepimas</h2>
                <p class="text-sm text-muted-foreground">
                    Jei teikėjas jums yra atlikęs darbą, papasakokite, kaip
                    sekėsi. Atsiliepimas bus pažymėtas „Pagal pakvietimą" ir
                    paskelbtas, kai jį peržiūrės administratorius. Jūsų pavardė
                    nerodoma.
                </p>
                <p v-if="expiresAt" class="text-xs text-muted-foreground">
                    Nuoroda galioja iki {{ formatDate(expiresAt) }}.
                </p>
            </div>
            <ReviewForm :action="page.url" submit-label="Siųsti atsiliepimą" />
        </section>

        <p
            v-else
            class="flex items-start gap-2 rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
            data-test="invitation-denied"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            {{ can.reason }}
        </p>
    </div>
</template>

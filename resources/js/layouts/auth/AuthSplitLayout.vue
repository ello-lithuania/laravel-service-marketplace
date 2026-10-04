<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BadgeCheck, MessagesSquare, Star } from '@lucide/vue';
import { computed } from 'vue';
import BrandLogo from '@/components/site/BrandLogo.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import { home } from '@/routes';

// Etapas 10: prisijungimo ir registracijos išdėstymas – forma kairėje, nuotrauka (SitePhotoKey::Auth) dešinėje.
// Nuotrauka ateina per bendrą prop'ą „site" (HandleInertiaRequests); nėra – tamsus prekės ženklo skydelis.
defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
const photo = computed(() => page.props.site?.auth_photo ?? null);

const points = [
    {
        icon: MessagesSquare,
        text: 'Pasiūlymai iš kelių teikėjų – vienoje vietoje',
    },
    { icon: Star, text: 'Tikri klientų atsiliepimai ir darbų nuotraukos' },
    { icon: BadgeCheck, text: 'Klientams – visiškai nemokamai' },
];
</script>

<template>
    <div
        class="grid min-h-svh bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]"
    >
        <div class="flex flex-col px-5 py-6 sm:px-10 lg:px-14 lg:py-8">
            <Link
                :href="home()"
                class="self-start rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                :aria-label="`${page.props.name} – pradžia`"
            >
                <BrandLogo />
            </Link>

            <main class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-md">
                    <div class="mb-8">
                        <h1
                            v-if="title"
                            class="text-3xl leading-tight font-bold text-balance sm:text-[2.1rem]"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mt-2 text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </main>

            <p class="text-xs text-muted-foreground">
                © {{ new Date().getFullYear() }} {{ page.props.name }}
            </p>
        </div>

        <!-- Dešinė pusė: tik plačiame ekrane -->
        <aside class="relative hidden p-4 lg:block" aria-hidden="true">
            <div
                class="relative isolate flex h-full flex-col justify-end overflow-hidden rounded-[2rem] bg-brand-deep p-10 text-white xl:p-12"
            >
                <PhotoSlot
                    v-if="photo"
                    :src="photo.url"
                    :alt="photo.alt ?? ''"
                    :width="1600"
                    :height="1200"
                    eager
                    img-class="-z-10 group-hover:scale-100"
                />
                <div
                    v-if="photo"
                    class="absolute inset-0 -z-0 bg-gradient-to-t from-black/80 via-black/30 to-black/5"
                />
                <div
                    v-else
                    class="absolute inset-0 -z-0 pattern-dots text-white/[0.06]"
                />

                <div class="relative max-w-lg">
                    <p
                        class="font-display text-[2.1rem] leading-[1.15] font-semibold text-balance xl:text-[2.5rem]"
                    >
                        Darbai namuose ir ne&nbsp;tik&nbsp;– patikimiems
                        meistrams.
                    </p>
                    <ul class="mt-8 space-y-3">
                        <li
                            v-for="point in points"
                            :key="point.text"
                            class="flex items-center gap-3 text-white/90"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20 backdrop-blur-sm"
                            >
                                <component
                                    :is="point.icon"
                                    class="size-4 text-cta"
                                />
                            </span>
                            {{ point.text }}
                        </li>
                    </ul>
                </div>
            </div>
        </aside>
    </div>
</template>

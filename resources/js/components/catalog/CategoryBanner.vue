<script setup lang="ts">
import { BadgeCheck, Plus, Users } from '@lucide/vue';
import CategoryIcon from '@/components/catalog/CategoryIcon.vue';
import PhotoSlot from '@/components/site/PhotoSlot.vue';
import { Button } from '@/components/ui/button';
import { toneFor } from '@/lib/brandTones';
import { plural } from '@/lib/format';

// Kategorijos puslapio viršus: plati nuotrauka (1600×700, image_wide_url) arba srities spalvų atsarginis dizainas.
// Nuotrauka dekoratyvi (alt=""), nes tą pačią informaciją perteikia antraštė.
const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        eyebrow: string;
        icon: string | null;
        slug: string;
        imageUrl: string | null;
        providersTotal: number;
        createRequestUrl: string;
        compact?: boolean;
    }>(),
    { compact: false },
);
</script>

<template>
    <header
        class="relative isolate flex flex-col justify-end overflow-hidden rounded-3xl bg-muted text-white shadow-lift"
        :class="
            compact
                ? 'min-h-[17rem] lg:min-h-[18.5rem]'
                : 'min-h-[20rem] lg:min-h-[24rem]'
        "
    >
        <PhotoSlot
            :src="imageUrl"
            alt=""
            :width="1600"
            :height="700"
            eager
            :tone="toneFor(props.icon, props.slug)"
            :icon="icon"
            sizes="(min-width: 1280px) 1216px, 100vw"
            img-class="-z-10 group-hover:scale-100"
        />
        <div
            class="absolute inset-0 -z-0 bg-gradient-to-t sm:bg-gradient-to-r"
            :class="
                imageUrl
                    ? 'from-black/80 via-black/50 to-black/10'
                    : 'from-black/35 via-black/10 to-transparent'
            "
            aria-hidden="true"
        />

        <div class="relative p-6 sm:p-10 lg:max-w-3xl lg:p-12">
            <p
                class="inline-flex items-center gap-2 rounded-full bg-white/15 py-1 pr-3 pl-1 text-sm font-medium ring-1 ring-white/25 backdrop-blur-sm"
            >
                <span
                    class="flex size-6 items-center justify-center rounded-full bg-white text-brand-deep"
                >
                    <CategoryIcon :name="icon" class="size-3.5" />
                </span>
                {{ eyebrow }}
            </p>
            <h1
                class="mt-4 leading-[1.05] font-bold text-balance"
                :class="
                    compact
                        ? 'text-3xl sm:text-4xl lg:text-[2.75rem]'
                        : 'text-4xl sm:text-5xl lg:text-[3.5rem]'
                "
            >
                {{ title }}
            </h1>
            <p
                class="mt-3 max-w-2xl text-base text-pretty text-white/85 sm:text-lg"
            >
                {{ description }}
            </p>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <Button variant="cta" size="xl" as-child>
                    <a :href="createRequestUrl">
                        <Plus class="size-5" aria-hidden="true" />
                        Sukurti užklausą
                    </a>
                </Button>
                <span
                    v-if="providersTotal > 0"
                    class="inline-flex items-center gap-2 rounded-full bg-black/25 px-3.5 py-2 text-sm ring-1 ring-white/20 backdrop-blur-sm"
                >
                    <Users class="size-4" aria-hidden="true" />
                    {{
                        plural(providersTotal, [
                            'teikėjas',
                            'teikėjai',
                            'teikėjų',
                        ])
                    }}
                </span>
                <span
                    class="hidden items-center gap-2 text-sm text-white/85 sm:inline-flex"
                >
                    <BadgeCheck class="size-4" aria-hidden="true" />
                    Pasiūlymai – nemokamai
                </span>
            </div>
        </div>
    </header>
</template>

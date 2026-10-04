<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, Quote } from '@lucide/vue';
import ProviderAvatar from '@/components/catalog/ProviderAvatar.vue';
import RatingStars from '@/components/catalog/RatingStars.vue';
import { show as providerShow } from '@/routes/providers';
import type { Testimonial } from '@/types';

// Tikras kliento atsiliepimas (SiteHighlights::testimonials). featured – didelė, tamsi kortelė skilties pradžioje.
withDefaults(defineProps<{ testimonial: Testimonial; featured?: boolean }>(), {
    featured: false,
});
</script>

<template>
    <!-- Didelė kortelė -->
    <figure
        v-if="featured"
        class="relative flex h-full min-w-0 flex-col rounded-3xl bg-brand-deep p-6 text-brand-deep-foreground shadow-lift sm:p-8 lg:p-10"
    >
        <Quote
            class="absolute top-6 right-6 size-14 text-cta/80 lg:top-8 lg:right-8 lg:size-20"
            aria-hidden="true"
        />
        <RatingStars :rating="testimonial.rating" class="size-5" />
        <blockquote
            class="mt-6 pr-10 font-display text-2xl leading-snug font-medium text-pretty text-white lg:text-[2rem] lg:leading-[1.25]"
        >
            <p>„{{ testimonial.comment }}“</p>
        </blockquote>
        <figcaption
            class="mt-auto flex flex-col gap-5 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between lg:mt-10"
        >
            <span class="flex min-w-0 items-center gap-3">
                <ProviderAvatar
                    :name="testimonial.author_name"
                    :src="null"
                    class="size-11 rounded-full ring-2 ring-white/15"
                    text-class="text-sm"
                />
                <span class="min-w-0 text-sm">
                    <span class="block font-semibold text-white">{{
                        testimonial.author_name
                    }}</span>
                    <span
                        v-if="testimonial.category || testimonial.city"
                        class="block truncate text-brand-deep-muted"
                    >
                        {{
                            [testimonial.category, testimonial.city]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </span>
                </span>
            </span>
            <Link
                :href="providerShow(testimonial.provider.slug)"
                class="group flex min-w-0 items-center justify-between gap-3 rounded-xl bg-white/[0.08] px-4 py-2.5 ring-1 ring-white/15 transition-colors hover:bg-white/[0.14]"
            >
                <span class="min-w-0">
                    <span class="block text-xs text-brand-deep-muted"
                        >Darbą atliko</span
                    >
                    <span
                        class="block truncate text-sm font-semibold text-white"
                        >{{ testimonial.provider.name }}</span
                    >
                </span>
                <ArrowUpRight
                    class="size-4 shrink-0 text-cta transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                    aria-hidden="true"
                />
            </Link>
        </figcaption>
    </figure>

    <!-- Įprasta kortelė -->
    <figure
        v-else
        class="relative flex h-full min-w-0 flex-col rounded-3xl border bg-card p-6 shadow-soft"
    >
        <Quote
            class="absolute top-6 right-6 size-8 text-primary/15"
            aria-hidden="true"
        />
        <RatingStars :rating="testimonial.rating" />
        <blockquote
            class="mt-4 flex-1 text-[0.9375rem] leading-relaxed text-pretty"
        >
            <p class="line-clamp-5">„{{ testimonial.comment }}“</p>
        </blockquote>
        <figcaption class="mt-6 flex items-center gap-3 border-t pt-5">
            <ProviderAvatar
                :name="testimonial.author_name"
                :src="null"
                class="size-10 rounded-full"
                text-class="text-sm"
            />
            <div class="min-w-0 text-sm">
                <p class="font-semibold">{{ testimonial.author_name }}</p>
                <p class="truncate text-muted-foreground">
                    apie
                    <Link
                        :href="providerShow(testimonial.provider.slug)"
                        class="relative font-medium text-foreground underline-offset-4 hover:underline"
                        >{{ testimonial.provider.name }}</Link
                    >
                </p>
            </div>
        </figcaption>
    </figure>
</template>

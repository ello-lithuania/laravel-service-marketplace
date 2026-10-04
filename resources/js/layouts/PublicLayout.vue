<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Menu,
    MessageSquareText,
    Search,
    ShieldCheck,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import BrandLogo from '@/components/site/BrandLogo.vue';
import HeaderSearch from '@/components/site/HeaderSearch.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { dashboard, home, login, pricing, register } from '@/routes';
import {
    index as categoriesIndex,
    show as categoryShow,
} from '@/routes/categories';
import { index as providersIndex } from '@/routes/providers';

// Viešos svetainės išdėstymas: lipni antraštė, turinys (slot) ir poraštė.
// Poraštės sritys ir miestai – bendras prop'as „site" (HandleInertiaRequests, Inertia::once).
const page = usePage();
const mobileMenuOpen = ref(false);
const searchOpen = ref(false);
const year = new Date().getFullYear();

// Perėjus į kitą puslapį paieškos juosta užsidaro
watch(
    () => page.url,
    () => {
        searchOpen.value = false;
    },
);

const user = computed(() => page.props.auth.user);
const site = computed(() => page.props.site);

// Užklausos kūrimo ir nuotraukų autorių puslapiai – paprastos nuorodos (ne Wayfinder funkcijos)
const createRequestUrl = '/uzklausos/nauja';
const photoCreditsUrl = '/nuotrauku-autoriai';

// anchor: true – paprasta <a> į pradžios puslapio skiltį; kitur – Inertia <Link> (be pilno perkrovimo)
// wide: true – rodoma tik plačiame ekrane (xl), kad antraštė netaptų per ankšta
const navLinks = [
    {
        label: 'Paslaugos',
        href: categoriesIndex.url(),
        anchor: false,
        wide: false,
    },
    {
        label: 'Meistrai',
        href: providersIndex.url(),
        anchor: false,
        wide: false,
    },
    {
        label: 'Kaip tai veikia',
        href: '/#kaip-tai-veikia',
        anchor: true,
        wide: true,
    },
    { label: 'Teikėjams', href: '/#teikejams', anchor: true, wide: true },
    { label: 'Kainos', href: pricing.url(), anchor: false, wide: false },
];

function isActive(href: string): boolean {
    const path = page.url.split('?')[0];

    return (
        !href.includes('#') && (path === href || path.startsWith(`${href}/`))
    );
}

// Pradžios puslapyje didelė paieška jau yra viršuje – antraštėje jos nekartojam
const showHeaderSearch = computed(() => page.component !== 'public/Home');
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <a
            href="#turinys"
            class="sr-only z-50 rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Pereiti prie turinio</a
        >

        <header
            class="sticky top-0 z-40 border-b border-border/70 bg-background/85 backdrop-blur-md supports-[backdrop-filter]:bg-background/75"
        >
            <div
                class="page-container flex h-16 items-center gap-4 lg:h-[4.5rem]"
            >
                <Link
                    :href="home()"
                    class="shrink-0 rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/40 focus-visible:outline-none"
                    :aria-label="`${page.props.name} – pradžia`"
                >
                    <BrandLogo />
                </Link>

                <nav
                    class="ml-4 hidden items-center gap-1 text-[0.9375rem] lg:flex"
                    aria-label="Pagrindinis meniu"
                >
                    <component
                        :is="link.anchor ? 'a' : Link"
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        :aria-current="isActive(link.href) ? 'page' : undefined"
                        class="rounded-full px-3 py-1.5 font-medium whitespace-nowrap text-foreground/75 transition-colors hover:bg-accent hover:text-foreground aria-[current=page]:bg-secondary aria-[current=page]:text-secondary-foreground"
                        :class="
                            link.wide ? 'hidden xl:inline-flex' : 'inline-flex'
                        "
                    >
                        {{ link.label }}
                    </component>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <!-- Paieška: labai plačiame ekrane – laukas, kitur – mygtukas, atveriantis juostą po antrašte -->
                    <HeaderSearch
                        v-if="showHeaderSearch"
                        class="hidden w-64 2xl:block"
                    />
                    <Button
                        v-if="showHeaderSearch"
                        variant="ghost"
                        size="icon"
                        class="hidden lg:inline-flex 2xl:hidden"
                        :aria-expanded="searchOpen"
                        aria-controls="header-search-panel"
                        :aria-label="
                            searchOpen
                                ? 'Uždaryti paiešką'
                                : 'Ieškoti paslaugos'
                        "
                        @click="searchOpen = !searchOpen"
                    >
                        <X v-if="searchOpen" class="size-5" />
                        <Search v-else class="size-5" />
                    </Button>

                    <template v-if="user">
                        <Button
                            variant="ghost"
                            class="hidden lg:inline-flex"
                            as-child
                        >
                            <Link :href="dashboard()">Mano paskyra</Link>
                        </Button>
                    </template>
                    <template v-else>
                        <Button
                            variant="ghost"
                            class="hidden lg:inline-flex"
                            as-child
                        >
                            <Link :href="login()">Prisijungti</Link>
                        </Button>
                        <Button
                            variant="outline"
                            class="hidden xl:inline-flex"
                            as-child
                        >
                            <Link :href="register()">Registruotis</Link>
                        </Button>
                    </template>

                    <Button
                        variant="cta"
                        class="hidden sm:inline-flex"
                        as-child
                    >
                        <a :href="createRequestUrl">Sukurti užklausą</a>
                    </Button>

                    <!-- Telefone ir planšetėje – meniu šoniniame skydelyje (Sheet: fokuso gaudyklė, Esc uždaro) -->
                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="lg:hidden"
                                aria-label="Atidaryti meniu"
                            >
                                <Menu class="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            class="w-[88%] gap-0 overflow-y-auto p-0 sm:max-w-sm"
                        >
                            <SheetHeader class="border-b px-5 py-4 text-left">
                                <SheetTitle class="sr-only">Meniu</SheetTitle>
                                <SheetDescription class="sr-only"
                                    >Svetainės skiltys ir
                                    paskyra</SheetDescription
                                >
                                <BrandLogo />
                            </SheetHeader>

                            <div class="space-y-6 px-5 py-5">
                                <HeaderSearch
                                    input-id="mobile-search"
                                    @submitted="mobileMenuOpen = false"
                                />

                                <nav
                                    class="flex flex-col"
                                    aria-label="Mobilusis meniu"
                                >
                                    <component
                                        :is="link.anchor ? 'a' : Link"
                                        v-for="link in navLinks"
                                        :key="link.href"
                                        :href="link.href"
                                        :aria-current="
                                            isActive(link.href)
                                                ? 'page'
                                                : undefined
                                        "
                                        class="flex items-center justify-between rounded-lg px-3 py-3 font-medium transition-colors hover:bg-accent aria-[current=page]:bg-secondary"
                                        @click="mobileMenuOpen = false"
                                    >
                                        {{ link.label }}
                                        <ArrowRight
                                            class="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                    </component>
                                </nav>

                                <div class="grid gap-2">
                                    <Button variant="cta" size="lg" as-child>
                                        <a :href="createRequestUrl"
                                            >Sukurti užklausą</a
                                        >
                                    </Button>
                                    <Button
                                        v-if="user"
                                        variant="outline"
                                        size="lg"
                                        as-child
                                    >
                                        <Link :href="dashboard()"
                                            >Mano paskyra</Link
                                        >
                                    </Button>
                                    <div v-else class="grid grid-cols-2 gap-2">
                                        <Button
                                            variant="outline"
                                            size="lg"
                                            as-child
                                        >
                                            <Link :href="login()"
                                                >Prisijungti</Link
                                            >
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            size="lg"
                                            as-child
                                        >
                                            <Link :href="register()"
                                                >Registruotis</Link
                                            >
                                        </Button>
                                    </div>
                                </div>

                                <p
                                    class="rounded-xl bg-secondary/70 p-4 text-sm text-secondary-foreground"
                                >
                                    Teikiate paslaugas?
                                    <Link
                                        :href="
                                            register({
                                                query: { role: 'provider' },
                                            })
                                        "
                                        class="font-semibold underline underline-offset-4"
                                        @click="mobileMenuOpen = false"
                                        >Užsiregistruokite kaip teikėjas</Link
                                    >
                                    ir gaukite užklausų iš klientų.
                                </p>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>

            <div
                v-if="searchOpen"
                id="header-search-panel"
                class="hidden border-t border-border/70 lg:block 2xl:hidden"
            >
                <div class="page-container flex items-center gap-4 py-3">
                    <HeaderSearch
                        autofocus
                        input-id="header-search-panel-input"
                        class="w-full max-w-xl"
                    />
                    <span class="text-sm text-muted-foreground"
                        >Pvz. santechnikas, plytelių klijavimas,
                        kraustymas</span
                    >
                </div>
            </div>
        </header>

        <main id="turinys" class="flex-1">
            <slot />
        </main>

        <footer
            class="relative overflow-hidden bg-brand-deep text-brand-deep-foreground"
        >
            <!-- Švelnus taškų raštas – kad tamsus plotas nebūtų „plokščias" -->
            <div
                class="pointer-events-none absolute inset-0 pattern-dots text-white/[0.04]"
                aria-hidden="true"
            />
            <div class="relative page-container py-14 lg:py-16">
                <div
                    class="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr] lg:gap-8"
                >
                    <div class="sm:col-span-2 lg:col-span-1">
                        <BrandLogo tone="light" />
                        <p
                            class="mt-4 max-w-xs text-sm leading-relaxed text-brand-deep-muted"
                        >
                            Meistrai ir paslaugų teikėjai visoje Lietuvoje.
                            Aprašykite darbą – pasiūlymus gausite nemokamai.
                        </p>
                        <ul class="mt-6 space-y-2.5 text-sm">
                            <li class="flex items-center gap-2.5">
                                <BadgeCheck
                                    class="size-4 text-cta"
                                    aria-hidden="true"
                                />
                                Teikėjų duomenis tikrina administracija
                            </li>
                            <li class="flex items-center gap-2.5">
                                <MessageSquareText
                                    class="size-4 text-cta"
                                    aria-hidden="true"
                                />
                                Atsiliepimus rašo tik klientai
                            </li>
                            <li class="flex items-center gap-2.5">
                                <ShieldCheck
                                    class="size-4 text-cta"
                                    aria-hidden="true"
                                />
                                Klientams – visiškai nemokamai
                            </li>
                        </ul>
                    </div>

                    <nav aria-labelledby="footer-services">
                        <h2
                            id="footer-services"
                            class="font-sans text-sm font-semibold tracking-normal text-white"
                        >
                            Paslaugos
                        </h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li
                                v-for="category in site?.categories.slice(0, 8)"
                                :key="category.id"
                            >
                                <Link
                                    :href="categoryShow(category.slug)"
                                    class="text-brand-deep-muted transition-colors hover:text-white"
                                    >{{ category.name }}</Link
                                >
                            </li>
                            <li>
                                <Link
                                    :href="categoriesIndex()"
                                    class="inline-flex items-center gap-1 font-medium text-white hover:underline"
                                >
                                    Visos paslaugos
                                    <ArrowRight
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                            </li>
                        </ul>
                    </nav>

                    <nav aria-labelledby="footer-cities">
                        <h2
                            id="footer-cities"
                            class="font-sans text-sm font-semibold tracking-normal text-white"
                        >
                            Meistrai mieste
                        </h2>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li v-for="city in site?.cities" :key="city.slug">
                                <Link
                                    :href="
                                        providersIndex({
                                            query: { miestas: city.slug },
                                        })
                                    "
                                    class="text-brand-deep-muted transition-colors hover:text-white"
                                    >{{ city.name }}</Link
                                >
                            </li>
                        </ul>
                    </nav>

                    <nav aria-labelledby="footer-providers">
                        <h2
                            id="footer-providers"
                            class="font-sans text-sm font-semibold tracking-normal text-white"
                        >
                            Teikėjams
                        </h2>
                        <ul
                            class="mt-4 space-y-2.5 text-sm text-brand-deep-muted"
                        >
                            <li>
                                <Link
                                    :href="
                                        register({
                                            query: { role: 'provider' },
                                        })
                                    "
                                    class="transition-colors hover:text-white"
                                    >Tapti teikėju</Link
                                >
                            </li>
                            <li>
                                <Link
                                    :href="pricing()"
                                    class="transition-colors hover:text-white"
                                    >Kainos ir kreditai</Link
                                >
                            </li>
                            <li>
                                <a
                                    href="/#teikejams"
                                    class="transition-colors hover:text-white"
                                    >Kaip gauti užsakymų</a
                                >
                            </li>
                            <li>
                                <Link
                                    :href="login()"
                                    class="transition-colors hover:text-white"
                                    >Prisijungti</Link
                                >
                            </li>
                        </ul>
                    </nav>

                    <nav aria-labelledby="footer-info">
                        <h2
                            id="footer-info"
                            class="font-sans text-sm font-semibold tracking-normal text-white"
                        >
                            Informacija
                        </h2>
                        <ul
                            class="mt-4 space-y-2.5 text-sm text-brand-deep-muted"
                        >
                            <li>
                                <a
                                    href="/#kaip-tai-veikia"
                                    class="transition-colors hover:text-white"
                                    >Kaip tai veikia</a
                                >
                            </li>
                            <li>
                                <a
                                    href="/#duk"
                                    class="transition-colors hover:text-white"
                                    >Dažni klausimai</a
                                >
                            </li>
                            <li>
                                <a
                                    :href="photoCreditsUrl"
                                    class="transition-colors hover:text-white"
                                    >Nuotraukų autoriai</a
                                >
                            </li>
                            <!-- Puslapiai, kurie atsiras vėliau (href="#") -->
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-white"
                                    >Apie mus</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-white"
                                    >Privatumo politika</a
                                >
                            </li>
                            <li>
                                <a
                                    href="#"
                                    class="transition-colors hover:text-white"
                                    >Kontaktai</a
                                >
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>

            <div class="relative border-t border-white/10">
                <div
                    class="page-container flex flex-col gap-2 py-5 text-xs text-brand-deep-muted sm:flex-row sm:items-center sm:justify-between"
                >
                    <p>
                        © {{ year }} {{ page.props.name }}. Visos teisės
                        saugomos.
                    </p>
                    <p>Paslaugos ir meistrai visoje Lietuvoje</p>
                </div>
            </div>
        </footer>
    </div>
</template>

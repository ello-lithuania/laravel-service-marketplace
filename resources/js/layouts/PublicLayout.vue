<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, X } from '@lucide/vue';
import { ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, pricing, register } from '@/routes';
import { index as categoriesIndex } from '@/routes/categories';
import { index as providersIndex } from '@/routes/providers';

// Viešos svetainės išdėstymas: antraštė, turinys (slot) ir poraštė.
// Nuorodos su href="#" – puslapiai, kurie atsiras vėlesniuose etapuose.
const page = usePage();
const mobileMenuOpen = ref(false);
const year = new Date().getFullYear();

// anchor: true – paprasta <a> į pradžios puslapio skiltį; kitur – Inertia <Link> (be pilno perkrovimo)
const navLinks = [
    // Katalogas (Etapas 4)
    { label: 'Paslaugos', href: categoriesIndex.url(), anchor: false },
    { label: 'Meistrai', href: providersIndex.url(), anchor: false },
    // Pradžios puslapio skiltys – su „/", kad veiktų ir iš kitų puslapių
    { label: 'Kaip tai veikia', href: '/#kaip-tai-veikia', anchor: true },
    { label: 'Teikėjams', href: '/#teikejams', anchor: true },
    // Etapas 7: kainų puslapis
    { label: 'Kainos', href: pricing.url(), anchor: false },
];
</script>

<template>
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-2 font-semibold"
                >
                    <span
                        class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </span>
                    {{ page.props.name }}
                </Link>

                <nav class="hidden items-center gap-6 text-sm md:flex">
                    <component
                        :is="link.anchor ? 'a' : Link"
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-muted-foreground transition-colors hover:text-foreground"
                    >
                        {{ link.label }}
                    </component>
                </nav>

                <div class="hidden items-center gap-2 md:flex">
                    <Button v-if="page.props.auth.user" as-child>
                        <Link :href="dashboard()">Mano paskyra</Link>
                    </Button>
                    <template v-else>
                        <Button variant="ghost" as-child>
                            <Link :href="login()">Prisijungti</Link>
                        </Button>
                        <Button as-child>
                            <Link :href="register()">Registruotis</Link>
                        </Button>
                    </template>
                </div>

                <Button
                    variant="ghost"
                    size="icon"
                    class="md:hidden"
                    :aria-label="
                        mobileMenuOpen ? 'Uždaryti meniu' : 'Atidaryti meniu'
                    "
                    @click="mobileMenuOpen = !mobileMenuOpen"
                >
                    <X v-if="mobileMenuOpen" />
                    <Menu v-else />
                </Button>
            </div>

            <div v-if="mobileMenuOpen" class="border-t px-4 py-4 md:hidden">
                <nav class="flex flex-col gap-3 text-sm">
                    <component
                        :is="link.anchor ? 'a' : Link"
                        v-for="link in navLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-muted-foreground hover:text-foreground"
                        @click="mobileMenuOpen = false"
                    >
                        {{ link.label }}
                    </component>
                </nav>
                <div class="mt-4 flex flex-col gap-2">
                    <Button v-if="page.props.auth.user" as-child>
                        <Link :href="dashboard()">Mano paskyra</Link>
                    </Button>
                    <template v-else>
                        <Button variant="outline" as-child>
                            <Link :href="login()">Prisijungti</Link>
                        </Button>
                        <Button as-child>
                            <Link :href="register()">Registruotis</Link>
                        </Button>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="border-t bg-muted/40">
            <div
                class="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm sm:grid-cols-2 md:grid-cols-4"
            >
                <div>
                    <p class="font-semibold">{{ page.props.name }}</p>
                    <p class="mt-2 text-muted-foreground">
                        Paslaugos ir meistrai visoje Lietuvoje.
                    </p>
                </div>
                <div>
                    <p class="font-medium">Klientams</p>
                    <ul class="mt-2 space-y-1 text-muted-foreground">
                        <li>
                            <a
                                href="/uzklausos/nauja"
                                class="hover:text-foreground"
                                >Sukurti užklausą</a
                            >
                        </li>
                        <li>
                            <a
                                href="/#kaip-tai-veikia"
                                class="hover:text-foreground"
                                >Kaip tai veikia</a
                            >
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="font-medium">Teikėjams</p>
                    <ul class="mt-2 space-y-1 text-muted-foreground">
                        <li>
                            <a href="/#teikejams" class="hover:text-foreground"
                                >Tapti teikėju</a
                            >
                        </li>
                        <li>
                            <Link
                                :href="pricing()"
                                class="hover:text-foreground"
                                >Kainos</Link
                            >
                        </li>
                    </ul>
                </div>
                <div>
                    <p class="font-medium">Informacija</p>
                    <ul class="mt-2 space-y-1 text-muted-foreground">
                        <li>
                            <a href="#" class="hover:text-foreground"
                                >Apie mus</a
                            >
                        </li>
                        <li>
                            <a href="#" class="hover:text-foreground"
                                >Privatumo politika</a
                            >
                        </li>
                        <li>
                            <a href="#" class="hover:text-foreground"
                                >Kontaktai</a
                            >
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t">
                <p
                    class="mx-auto max-w-6xl px-4 py-4 text-xs text-muted-foreground"
                >
                    © {{ year }} {{ page.props.name }}. Visos teisės saugomos.
                </p>
            </div>
        </footer>
    </div>
</template>

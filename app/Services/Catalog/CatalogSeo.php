<?php

namespace App\Services\Catalog;

use App\Models\ProviderProfile;

/**
 * Katalogo puslapių SEO tekstai vienoje vietoje: administratoriaus įrašyti (meta_title, meta_description)
 * arba sugeneruoti pagal šabloną.
 *
 * Canonical – pagrindinis puslapio adresas be filtrų ir rikiavimo: „?patikrinti=1&rikiuoti=…" yra to paties
 * turinio variantai, todėl Google turi juos laikyti vienu puslapiu. Puslapio numeris paliekamas – kiti
 * puslapiai rodo kitus teikėjus.
 */
class CatalogSeo
{
    public function home(): SeoMeta
    {
        return new SeoMeta(
            title: 'Paslaugos ir meistrai visoje Lietuvoje',
            description: 'Raskite patikimą meistrą ar paslaugų teikėją savo mieste: palyginkite atsiliepimus ir kainas, '
                .'aprašykite darbą ir gaukite pasiūlymus nemokamai.',
            canonical: route('home'),
        );
    }

    public function categoriesIndex(): SeoMeta
    {
        return new SeoMeta(
            title: 'Visos paslaugos',
            description: 'Visų paslaugų katalogas: statyba ir remontas, santechnika, elektra, valymas, perkraustymas, '
                .'grožis, IT, renginiai ir kt. Išsirinkite kategoriją ir raskite meistrą.',
            canonical: route('categories.index'),
        );
    }

    /**
     * „Plytelių klijavimas" arba „Plytelių klijavimas Vilniuje" (cities.name_locative).
     */
    public function category(CachedCategory $category, ?CachedCity $city, int $page, int $total): SeoMeta
    {
        if ($city !== null) {
            $title = $category->name.' '.$city->nameLocative;
            $description = $title.': palyginkite meistrų kainas, atsiliepimus ir atliktus darbus. '
                .'Aprašykite darbą ir gaukite pasiūlymus nemokamai.';
            $canonical = route('categories.city', ['category' => $category->slug, 'city' => $city->slug]);
        } else {
            $title = $category->metaTitle ?? $category->name.': meistrai, kainos ir atsiliepimai';
            $description = $category->metaDescription ?? $category->description
                ?? $category->name.': palyginkite meistrų kainas, atsiliepimus ir atliktus darbus visoje Lietuvoje. '
                .'Aprašykite darbą ir gaukite pasiūlymus nemokamai.';
            $canonical = route('categories.show', ['category' => $category->slug]);
        }

        return new SeoMeta(
            title: $this->withPage($title, $page),
            description: $description,
            canonical: $this->withPageQuery($canonical, $page),
            // Tuščias „paslauga mieste" puslapis – „plonas" turinys, jo neindeksuojam
            indexable: $total > 0 || $city === null,
        );
    }

    public function providers(?CachedCity $city, int $page): SeoMeta
    {
        $title = $city === null ? 'Visi meistrai ir paslaugų teikėjai' : 'Meistrai ir paslaugų teikėjai '.$city->nameLocative;
        $query = $city === null ? [] : ['miestas' => $city->slug];

        return new SeoMeta(
            title: $this->withPage($title, $page),
            description: $title.': patikrinti teikėjai, klientų atsiliepimai, kainos ir atlikti darbai.',
            canonical: $this->withPageQuery(route('providers.index', $query), $page),
        );
    }

    public function provider(ProviderProfile $provider): SeoMeta
    {
        $title = $provider->display_name.($provider->headline ? ' – '.$provider->headline : '');

        $rating = $provider->reviews_count > 0
            ? sprintf('Įvertinimas %s iš 5, atsiliepimų: %d. ', number_format((float) $provider->rating_avg, 1, ',', ''), $provider->reviews_count)
            : '';

        return new SeoMeta(
            title: $title,
            description: $provider->display_name.'. '.$rating.($provider->description ?? $provider->headline ?? ''),
            canonical: route('providers.show', ['providerProfile' => $provider->slug]),
        );
    }

    /**
     * Vidinės paieškos rezultatų neindeksuojam (Google rekomendacija): jų begalė ir jie dubliuoja katalogą.
     */
    public function search(string $query): SeoMeta
    {
        return new SeoMeta(
            title: 'Paieška: '.$query,
            description: 'Paieškos „'.$query.'" rezultatai: paslaugų kategorijos ir teikėjai.',
            canonical: route('search', ['q' => $query]),
            indexable: false,
        );
    }

    private function withPage(string $title, int $page): string
    {
        return $page > 1 ? $title.' – '.$page.' puslapis' : $title;
    }

    private function withPageQuery(string $url, int $page): string
    {
        if ($page <= 1) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').ProviderListQuery::PAGE_NAME.'='.$page;
    }
}

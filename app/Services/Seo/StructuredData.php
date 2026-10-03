<?php

namespace App\Services\Seo;

use App\Models\City;
use App\Models\ProviderProfile;
use Illuminate\Support\Str;

/**
 * Struktūriniai duomenys (schema.org, JSON-LD) – mašinai skaitoma puslapio santrauka. Google iš jų rodo
 * „praturtintus" rezultatus: duonos trupinius, žvaigždutes, svetainės paieškos laukelį.
 *
 * Grąžinami paprasti masyvai; SeoMeta juos sujungia į vieną @graph ir užkoduoja JSON. Rodoma tik tai, kas matoma
 * ir pačiame puslapyje (Google taisyklė: struktūriniai duomenys negali „pasakoti" daugiau nei puslapis).
 * https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data
 */
final class StructuredData
{
    /**
     * Svetainė su paieška: Google gali rodyti paieškos laukelį po svetainės pavadinimu.
     *
     * @return array<string, mixed>
     */
    public static function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => route('home').'#website',
            'name' => config('app.name'),
            'url' => route('home'),
            'inLanguage' => 'lt-LT',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => route('home').'#organization',
            'name' => config('app.name'),
            'url' => route('home'),
            'logo' => asset('apple-touch-icon.png'),
        ];
    }

    /**
     * „Duonos trupiniai": Pradžia → Paslaugos → … → dabartinis puslapis.
     *
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ], $items, array_keys($items)),
        ];
    }

    /**
     * Teikėjas – ProfessionalService (LocalBusiness potipis): pavadinimas, vieta, aptarnaujamos vietovės ir
     * įvertinimas. Gatvės adreso nerodom (jo neturim ir jis privatus) – tik miestą.
     * Naudojami tik jau užkrauti ryšiai (city, serviceAreas, media): preventLazyLoading neleistų jų krauti čia.
     *
     * @return array<string, mixed>
     */
    public static function provider(ProviderProfile $provider, string $url): array
    {
        $data = [
            '@type' => 'ProfessionalService',
            '@id' => $url.'#business',
            'name' => $provider->display_name,
            'url' => $url,
            'description' => Str::limit(Str::squish((string) ($provider->headline ?? $provider->description ?? '')), 300, '…'),
        ];

        $image = $provider->relationLoaded('media') ? ($provider->logoUrl() ?? $provider->coverUrl()) : null;

        if ($image !== null) {
            $data['image'] = $image;
        }

        if ($provider->relationLoaded('city') && $provider->city instanceof City) {
            $data['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $provider->city->name,
                'addressCountry' => 'LT',
            ];
        }

        if ($provider->serves_whole_country) {
            $data['areaServed'] = ['@type' => 'Country', 'name' => 'Lietuva'];
        } elseif ($provider->relationLoaded('serviceAreas') && $provider->serviceAreas->isNotEmpty()) {
            $data['areaServed'] = $provider->serviceAreas
                ->map(fn (City $city): array => ['@type' => 'City', 'name' => $city->name])
                ->values()
                ->all();
        }

        // Be atsiliepimų įvertinimo nerodom: „0 iš 5" Google laikytų klaida
        if ($provider->reviews_count > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format((float) $provider->rating_avg, 2, '.', ''),
                'reviewCount' => $provider->reviews_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ];
        }

        return $data;
    }
}

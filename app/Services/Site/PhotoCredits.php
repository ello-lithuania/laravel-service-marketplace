<?php

namespace App\Services\Site;

use App\Enums\SitePhotoKey;
use App\Models\Category;
use App\Models\SitePhoto;
use App\Services\Catalog\ProviderListQuery;
use App\Services\Photos\PhotoCredit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Puslapio „Nuotraukų autoriai" duomenys: visos media su custom_properties.credit (docs/DB_SCHEMA.md → media).
 *
 * Trys grupės: svetainės nuotraukos, kategorijų nuotraukos ir demo duomenys (portfolio, viršeliai). Demo rinkinio
 * nuotrauka prisegama prie daugelio darbų, todėl ten grupuojam pagal stock_id – kiekviena nuotrauka rodoma vieną
 * kartą – ir puslapiuojam.
 *
 * @phpstan-type CreditItem array{id: int, thumb_url: string, label: string|null, title: string|null, author: string|null, author_url: string|null, source: string, source_url: string|null, license: string, license_url: string|null}
 */
final class PhotoCredits
{
    /** Demo media, kurių nuotraukos gali būti iš portfolio rinkinio (MediaGenerator). */
    private const DEMO_MODELS = ['portfolio_item', 'provider_profile'];

    /**
     * @return list<CreditItem>
     */
    public function sitePhotos(): array
    {
        $media = $this->credited()->where('model_type', 'site_photo')->get()->groupBy('model_id');
        $places = SitePhoto::query()->whereKey($media->keys())->get()->keyBy(fn (SitePhoto $place): string => $place->key->value);

        $items = [];

        // Tvarka – kaip SitePhotoKey enum'e (pradžios puslapio viršus pirmas)
        foreach (SitePhotoKey::cases() as $key) {
            $place = $places->get($key->value);

            foreach ($place === null ? [] : $media->get($place->id, []) as $item) {
                $items[] = $this->item($item, $key->label());
            }
        }

        return array_values(array_filter($items));
    }

    /**
     * @return list<CreditItem>
     */
    public function categories(): array
    {
        $media = $this->credited()->where('model_type', 'category')->get()->groupBy('model_id');

        $categories = Category::query()
            ->whereKey($media->keys())
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $items = [];

        foreach ($categories as $category) {
            foreach ($media->get($category->id, []) as $item) {
                $items[] = $this->item($item, $category->name);
            }
        }

        return array_values(array_filter($items));
    }

    /**
     * Demo rinkinio nuotraukos – kiekviena vieną kartą (pirmasis media įrašas su tuo stock_id).
     *
     * @return LengthAwarePaginator<int, CreditItem|null> null – įrašas dingo tarp užklausų arba credit sugadintas
     */
    public function demo(int $perPage): LengthAwarePaginator
    {
        // GROUP BY JSON reikšme: Laravel „custom_properties->stock_id" paverčia json_extract() (SQLite) arba
        // json_unquote(json_extract()) (MySQL), todėl ta pati užklausa veikia abiejose DB
        $page = Media::query()
            ->selectRaw('MIN(id) as id')
            ->whereIn('model_type', self::DEMO_MODELS)
            ->whereNotNull('custom_properties->credit')
            ->whereNotNull('custom_properties->stock_id')
            ->groupBy('custom_properties->stock_id')
            ->orderBy('id')
            ->paginate($perPage, pageName: ProviderListQuery::PAGE_NAME)
            ->withQueryString();

        // Puslapio eilutės turi tik id – pilni įrašai viena užklausa
        $media = Media::query()->findMany($page->getCollection()->modelKeys())->keyBy('id');

        /** @var LengthAwarePaginator<int, CreditItem|null> $items */
        $items = $page->through(fn (Media $row): ?array => ($found = $media->get($row->getKey())) instanceof Media
            ? $this->item($found, null)
            : null);

        return $items;
    }

    /**
     * @return Builder<Media>
     */
    private function credited(): Builder
    {
        return Media::query()->whereNotNull('custom_properties->credit')->orderBy('id');
    }

    /**
     * Tik reikalingi laukai (ne visas Media modelis): miniatiūra ir autoriaus duomenys.
     *
     * @return CreditItem|null
     */
    private function item(Media $media, ?string $label): ?array
    {
        $credit = PhotoCredit::fromMedia($media);

        if ($credit === null) {
            return null;
        }

        return [
            'id' => (int) $media->id,
            'thumb_url' => $this->thumbUrl($media),
            'label' => $label,
            ...$credit->toArray(),
        ];
    }

    /**
     * Mažiausia jau padaryta miniatiūra (portfolio – thumb, kategorija ir svetainė – card, viršelis – wide).
     */
    private function thumbUrl(Media $media): string
    {
        foreach (['thumb', 'card', 'wide'] as $conversion) {
            if ($media->hasGeneratedConversion($conversion)) {
                return $media->getUrl($conversion);
            }
        }

        return $media->getUrl();
    }
}

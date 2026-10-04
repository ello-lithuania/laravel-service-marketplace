<?php

namespace App\Filament\Resources\SitePhotos\Pages;

use App\Enums\SitePhotoKey;
use App\Filament\Resources\SitePhotos\SitePhotoResource;
use App\Models\SitePhoto;
use Filament\Resources\Pages\ManageRecords;

class ManageSitePhotos extends ManageRecords
{
    protected static string $resource = SitePhotoResource::class;

    public function mount(): void
    {
        // Eilutė kiekvienai vietai: naujas SitePhotoKey case atsiranda sąraše be migracijos ar seed'o
        foreach (SitePhotoKey::cases() as $key) {
            SitePhoto::forKey($key);
        }

        parent::mount();
    }

    public function getSubheading(): string
    {
        return 'Didelės nuotraukos svetainės dizaine. Kol nuotraukos nėra, puslapis rodo atsarginį dizainą. '
            .'Nemokamas nuotraukas (su autoriais) atsisiunčia komanda „php artisan photos:download".';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}

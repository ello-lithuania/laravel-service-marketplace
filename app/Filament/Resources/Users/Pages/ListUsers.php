<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Skirtukai pagal rolę. Skaitliukų (badge) sąmoningai nėra: COUNT per 80 000 vartotojų kiekvieną kartą
     * atidarant sąrašą – bereikalinga apkrova (suvestinė su cache – skydelyje).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Visi'),
            'clients' => Tab::make('Klientai')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::Client)),
            'providers' => Tab::make('Teikėjai')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::Provider)),
            'admins' => Tab::make('Administratoriai')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::Admin)),
            'banned' => Tab::make('Užblokuoti')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('banned_at')),
        ];
    }
}

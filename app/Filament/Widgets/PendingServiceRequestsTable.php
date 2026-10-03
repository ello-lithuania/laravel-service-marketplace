<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Naujausios užklausos, laukiančios moderavimo (pending). Paspaudus – užklausos peržiūra su „Patvirtinti" / „Atmesti".
 * Užklausa naudoja indeksą (status, published_at): pending užklausų – šimtai, ne tūkstančiai, todėl cache nereikia.
 * https://filamentphp.com/docs/5.x/widgets/overview#table-widgets
 */
class PendingServiceRequestsTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Laukia patvirtinimo')
            ->query(fn () => ServiceRequest::query()
                ->where('status', ServiceRequestStatus::Pending)
                ->with(['client:id,first_name,last_name', 'category:id,name'])
                ->latest()
                ->limit(5))
            ->paginated(false)
            ->emptyStateHeading('Laukiančių užklausų nėra')
            ->recordUrl(fn (ServiceRequest $record): string => ServiceRequestResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('title')
                    ->label('Užklausa')
                    ->limit(40)
                    ->description(fn (ServiceRequest $record): string => $record->category->name),
                TextColumn::make('client.name')->label('Klientas'),
                TextColumn::make('created_at')->label('Sukurta')->since(),
            ]);
    }
}

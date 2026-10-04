<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Actions\CreditNoteAction;
use App\Filament\Resources\Payments\Actions\InvoiceAction;
use App\Filament\Resources\Payments\Actions\RefundPaymentAction;
use App\Models\Payment;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sukurta')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Mokėtojas')
                    ->description(fn (Payment $record): ?string => $record->user?->email),
                // Paieška pagal el. paštą (ryšio stulpelis), nors pats stulpelis paslėptas
                TextColumn::make('user.email')
                    ->label('El. paštas')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchasable_type')
                    ->label('Pirkinys')
                    ->state(fn (Payment $record): string => $record->description()),
                TextColumn::make('amount_cents')
                    ->label('Suma')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('gateway')
                    ->label('Tiekėjas')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Būsena')
                    ->badge()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Apmokėta')
                    ->dateTime('Y-m-d H:i', 'Europe/Vilnius')
                    ->placeholder('–')
                    ->sortable(),
                TextColumn::make('invoice_number')
                    ->label('Sąskaita')
                    ->placeholder('–')
                    // Etapas 9b: grąžinto mokėjimo kreditinės sąskaitos numeris po sąskaitos numeriu
                    ->description(fn (Payment $record): ?string => $record->refund?->credit_note_number)
                    ->searchable(),
                TextColumn::make('uuid')
                    ->label('Užsakymo Nr.')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Būsena')
                    ->options(PaymentStatus::class),
                SelectFilter::make('gateway')
                    ->label('Tiekėjas')
                    ->options(PaymentGateway::class),
                // Datų intervalas: forma filtre (schema) + savo užklausos sąlyga (query)
                Filter::make('created_at')
                    ->label('Data')
                    ->schema([
                        DatePicker::make('from')->label('Nuo'),
                        DatePicker::make('until')->label('Iki'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data): array => array_values(array_filter([
                        isset($data['from']) ? 'Nuo '.$data['from'] : null,
                        isset($data['until']) ? 'Iki '.$data['until'] : null,
                    ]))),
            ])
            ->recordActions([
                ViewAction::make(),
                InvoiceAction::make(),
                // --- Etapas 9b ---
                CreditNoteAction::make(),
                RefundPaymentAction::make(),
            ]);
    }
}

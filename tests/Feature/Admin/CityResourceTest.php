<?php

use App\Filament\Resources\Cities\Pages\ManageCities;
use App\Models\City;
use App\Models\Region;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('savivaldybių sąrašas atsidaro ir rodo įrašus', function () {
    $cities = City::factory()->count(3)->create();

    $this->get('/admin/savivaldybes')->assertOk()->assertSee('Savivaldybės');

    Livewire::test(ManageCities::class)->assertCanSeeTableRecords($cities);
});

test('savivaldybę galima sukurti modaliniame lange', function () {
    $region = Region::factory()->create();

    Livewire::test(ManageCities::class)
        ->callAction(CreateAction::class, data: [
            'region_id' => $region->id,
            'name' => 'Birštonas',
            'name_locative' => 'Birštone',
            'slug' => 'birstonas',
            'sort_order' => 50,
        ])
        ->assertHasNoFormErrors();

    expect(City::query()->where('slug', 'birstonas')->first())
        ->not->toBeNull()
        ->name_locative->toBe('Birštone')
        ->region_id->toBe($region->id);
});

test('savivaldybės vietininką galima pataisyti', function () {
    $city = City::factory()->create();

    Livewire::test(ManageCities::class)
        ->callAction(TestAction::make(EditAction::class)->table($city), data: ['name_locative' => 'Pataisyta'])
        ->assertHasNoFormErrors();

    expect($city->refresh()->name_locative)->toBe('Pataisyta');
});

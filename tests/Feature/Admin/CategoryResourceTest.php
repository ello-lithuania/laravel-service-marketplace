<?php

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

test('kategorijų sąrašas rodo visų lygių kategorijas', function () {
    $leaf = Category::factory()->leaf()->create();

    $this->get('/admin/kategorijos')->assertOk()->assertSee('Kategorijos');

    Livewire::test(ListCategories::class)
        ->assertCanSeeTableRecords(Category::query()->get())
        ->filterTable('depth', 3)
        ->assertCanSeeTableRecords([$leaf])
        ->assertCanNotSeeTableRecords([$leaf->parent_id]);
});

test('kuriant kategoriją lygis paskaičiuojamas iš tėvo', function () {
    $group = Category::factory()->childOf(Category::factory()->create())->create();

    Livewire::test(CreateCategory::class)
        ->fillForm([
            'parent_id' => $group->id,
            'name' => 'Plytelių klijavimas',
            'slug' => 'plyteliu-klijavimas',
            'offer_cost_credits' => 2,
            'sort_order' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $category = Category::query()->where('slug', 'plyteliu-klijavimas')->firstOrFail();

    expect($category->depth)->toBe(3)
        ->and($category->parent_id)->toBe($group->id)
        ->and($category->offer_cost_credits)->toBe(2);
});

test('slug turi būti unikalus, pavadinimas privalomas', function () {
    Category::factory()->create(['slug' => 'santechnika']);

    Livewire::test(CreateCategory::class)
        ->fillForm(['name' => '', 'slug' => 'santechnika'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'slug' => 'unique']);
});

test('kategoriją galima redaguoti ir išjungti', function () {
    $category = Category::factory()->create();

    Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['name' => 'Naujas pavadinimas', 'is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($category->refresh())
        ->name->toBe('Naujas pavadinimas')
        ->is_active->toBeFalse();
});

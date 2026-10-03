<?php

use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Avataras, logotipas ir viršelis (spatie/laravel-medialibrary).
 * Storage::fake('public') – failai rašomi į laikiną „netikrą" diską, ne į storage/app/public.
 */
beforeEach(function () {
    Storage::fake('public');
});

test('vartotojas įkelia avatarą; sukuriama miniatiūra, o media.model_type – trumpas vardas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('avatar.update'), ['image' => UploadedFile::fake()->image('foto.jpg', 400, 400)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $media = $user->getFirstMedia('avatar');

    expect($media)->not->toBeNull()
        // enforceMorphMap: DB saugomas „user", o ne „App\Models\User"
        ->and($media->getRawOriginal('model_type'))->toBe('user')
        ->and($media->hasGeneratedConversion('thumb'))->toBeTrue();

    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot('thumb'));
});

test('naujas avataras pakeičia senąjį (singleFile), senas failas ištrinamas', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('avatar.update'), ['image' => UploadedFile::fake()->image('a.jpg', 300, 300)]);
    $oldPath = $user->getFirstMedia('avatar')->getPathRelativeToRoot();

    $this->actingAs($user)->post(route('avatar.update'), ['image' => UploadedFile::fake()->image('b.png', 300, 300)]);

    expect($user->fresh()->getMedia('avatar'))->toHaveCount(1)
        ->and(Media::count())->toBe(1);
    Storage::disk('public')->assertMissing($oldPath);
});

test('avatarą galima pašalinti', function () {
    $user = User::factory()->create();
    $user->addMedia(UploadedFile::fake()->image('a.jpg', 300, 300))->toMediaCollection('avatar');

    $this->actingAs($user)->delete(route('avatar.destroy'))->assertRedirect();

    expect($user->fresh()->avatarUrl())->toBeNull();
});

test('griežta failų validacija', function (Closure $file, string $message) {
    $this->actingAs(User::factory()->create())
        ->post(route('avatar.update'), ['image' => $file()])
        ->assertSessionHasErrors(['image' => $message]);

    expect(Media::count())->toBe(0);
})->with([
    'ne paveikslėlis' => [fn () => UploadedFile::fake()->create('dokumentas.pdf', 100, 'application/pdf'), 'Tinka tik JPG, PNG arba WEBP nuotraukos.'],
    'SVG (gali turėti JavaScript)' => [fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'Tinka tik JPG, PNG arba WEBP nuotraukos.'],
    // Tikras failas (ne fake()): fake() MIME tipą spėja iš plėtinio, o tikras – iš turinio (finfo)
    'PHP failas su .jpg plėtiniu' => [function () {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<?php echo "hack";');

        return new UploadedFile($path, 'foto.jpg', null, null, true);
    }, 'Tinka tik JPG, PNG arba WEBP nuotraukos.'],
    'per didelis' => [fn () => UploadedFile::fake()->image('didele.jpg', 400, 400)->size(6 * 1024), 'Nuotrauka per didelė – daugiausia 5 MB.'],
    'per mažas' => [fn () => UploadedFile::fake()->image('maza.jpg', 100, 100), 'Nuotrauka turi būti ne mažesnė nei 200×200 ir ne didesnė nei 8000×8000 taškų.'],
    'nepasirinktas' => [fn () => null, 'Pasirinkite nuotrauką.'],
]);

test('teikėjas įkelia logotipą ir viršelį', function () {
    $profile = ProviderProfile::factory()->create();

    $this->actingAs($profile->user)
        ->post(route('provider.images.update', 'logotipas'), ['image' => UploadedFile::fake()->image('logo.png', 500, 300)])
        ->assertSessionHasNoErrors();
    $this->actingAs($profile->user)
        ->post(route('provider.images.update', 'virselis'), ['image' => UploadedFile::fake()->image('cover.jpg', 1600, 900)])
        ->assertSessionHasNoErrors();

    $profile->refresh();
    expect($profile->getFirstMedia('logo')->getRawOriginal('model_type'))->toBe('provider_profile')
        ->and($profile->logoUrl())->toContain('-thumb.')
        ->and($profile->coverUrl())->toContain('-wide.');

    // Miniatiūros dydžiai pagal Fit taisykles
    [$width, $height] = getimagesize(Storage::disk('public')->path($profile->getFirstMedia('cover')->getPathRelativeToRoot('wide')));
    expect([$width, $height])->toBe([1200, 400]);

    $this->actingAs($profile->user)->get(route('provider.images.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/profile/Images')
            ->where('logo', $profile->logoUrl())
            ->where('cover', $profile->coverUrl()));

    $this->actingAs($profile->user)->delete(route('provider.images.destroy', 'logotipas'));
    expect($profile->fresh()->logoUrl())->toBeNull();
});

test('nežinoma kolekcija – 404, klientas logotipo įkelti negali', function () {
    $profile = ProviderProfile::factory()->create();

    $this->actingAs($profile->user)
        ->post('/paskyra/profilis/nuotraukos/kita', ['image' => UploadedFile::fake()->image('x.jpg', 300, 300)])
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->post(route('provider.images.update', 'logotipas'), ['image' => UploadedFile::fake()->image('x.jpg', 300, 300)])
        ->assertForbidden();
});

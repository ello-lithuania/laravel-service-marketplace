<?php

use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Messaging;

/**
 * Žinučių priedai (Etapas 6): nuotraukos ir PDF privačiame diske, atiduodami tik dalyviams.
 */
beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
    $this->offer = Messaging::offer();
    $this->conversation = Messaging::conversation($this->offer);
    $this->client = Messaging::client($this->offer);
});

/**
 * Tikras (mažas) PDF: medialibrary tipą tikrina pagal turinį, todėl tuščias netikras failas netiktų.
 * Didelis – tik dydžiui patikrinti (validacija jį atmeta anksčiau, nei pasiektų medialibrary).
 */
function pdf(string $name = 'samata.pdf', ?int $kilobytes = null): UploadedFile
{
    return $kilobytes === null
        ? UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n")
        : UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

test('žinutė su nuotrauka ir PDF – priedai privačiame diske, tekstas neprivalomas', function () {
    $this->actingAs($this->client)
        ->post(route('messages.store', $this->conversation), [
            'body' => '',
            'attachments' => [UploadedFile::fake()->image('vonia.jpg', 800, 600), pdf()],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $message = Message::query()->sole();
    $media = $message->getMedia('attachments');

    expect($message->body)->toBe('')
        ->and($media)->toHaveCount(2)
        ->and($media->pluck('disk')->unique()->all())->toBe(['local'])
        ->and($media->pluck('mime_type')->sort()->values()->all())->toBe(['application/pdf', 'image/jpeg']);
    Storage::disk('local')->assertExists($media[0]->getPathRelativeToRoot());
    // Viešame diske nieko neatsirado
    expect(Storage::disk('public')->allFiles())->toBe([]);

    $this->actingAs($this->client)->get(route('conversations.show', $this->conversation))
        ->assertInertia(fn (Assert $page) => $page
            ->has('messages.0.attachments', 2)
            ->where('messages.0.attachments.0.is_image', true)
            ->where('messages.0.attachments.1.is_image', false)
            ->where('messages.0.attachments.1.thumb_url', null));
});

test('netinkami priedai atmetami', function (array $files, string $errorKey) {
    $this->actingAs($this->client)
        ->post(route('messages.store', $this->conversation), ['body' => 'Žr. priedą', 'attachments' => $files])
        ->assertSessionHasErrors($errorKey);

    expect(Message::query()->count())->toBe(0);
})->with([
    'vykdomasis failas' => [fn () => [UploadedFile::fake()->create('virusas.exe', 10, 'application/x-msdownload')], 'attachments.0'],
    'SVG (gali turėti JavaScript)' => [fn () => [UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')], 'attachments.0'],
    'per didelis PDF' => [fn () => [pdf('didelis.pdf', 11 * 1024)], 'attachments.0'],
    'per maža nuotrauka' => [fn () => [UploadedFile::fake()->image('mazas.jpg', 50, 50)], 'attachments.0'],
    'per daug priedų' => [fn () => array_map(fn (int $i) => pdf("f{$i}.pdf"), range(1, 6)), 'attachments'],
]);

test('priedą atsisiunčia tik pokalbio dalyviai ir administratorius', function () {
    $message = Messaging::send($this->conversation, Messaging::provider($this->offer), 'Sąmata');
    $media = $message->addMedia(pdf())->toMediaCollection('attachments');
    $url = route('media.show', ['media' => $media->id]);

    $this->actingAs($this->client)->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('samata.pdf');

    $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs(User::factory()->provider()->create())->get($url)->assertForbidden();
});

test('nuotraukos miniatiūra rodoma naršyklėje; svečias nukreipiamas prisijungti', function () {
    $message = Messaging::send($this->conversation, $this->client, 'Nuotrauka');
    $media = $message->addMedia(UploadedFile::fake()->image('siena.png', 600, 400))->toMediaCollection('attachments');

    $this->actingAs(Messaging::provider($this->offer))
        ->get(route('media.show', ['media' => $media->id, 'conversion' => 'thumb']))
        ->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=siena.png');

    auth()->logout();
    $this->get(route('media.show', ['media' => $media->id]))->assertRedirect(route('login'));
});

test('paslėptos žinutės priedas nebeatiduodamas', function () {
    $message = Messaging::send($this->conversation, $this->client, 'Nuotrauka');
    $media = $message->addMedia(pdf())->toMediaCollection('attachments');
    $message->delete();

    $this->actingAs($this->client)->get(route('media.show', ['media' => $media->id]))->assertNotFound();
});

test('vieši failai (avataras) per šį maršrutą neatiduodami', function () {
    $avatar = $this->client->addMedia(UploadedFile::fake()->image('a.jpg', 300, 300))->toMediaCollection('avatar');

    $this->actingAs($this->client)->get(route('media.show', ['media' => $avatar->id]))->assertNotFound();
});

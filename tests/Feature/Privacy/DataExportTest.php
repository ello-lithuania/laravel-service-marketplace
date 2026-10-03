<?php

use App\Enums\CreditTransactionType;
use App\Jobs\GenerateUserDataExport;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Offer;
use App\Models\Payment;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\DataExportReady;
use App\Services\Credits\CreditLedger;
use App\Services\Privacy\DataExportStorage;
use App\Services\Privacy\UserDataExporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * Paleidžia eksporto job'ą ir grąžina archyvo turinį: [failų vardai, duomenys.json masyvas].
 *
 * @return array{0: list<string>, 1: array<string, mixed>}
 */
function runExport(User $user): array
{
    (new GenerateUserDataExport($user))->handle(app(UserDataExporter::class), app(DataExportStorage::class));

    $path = Storage::disk('local')->allFiles("data-exports/{$user->id}")[0];
    $local = tempnam(sys_get_temp_dir(), 'zip');
    file_put_contents($local, Storage::disk('local')->get($path));

    $zip = new ZipArchive;
    $zip->open($local);
    $names = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $names[] = (string) $zip->getNameIndex($i);
    }
    $data = json_decode((string) $zip->getFromName('duomenys.json'), true);
    $zip->close();
    unlink($local);

    return [$names, $data];
}

test('privatumo puslapis – tik prisijungusiems', function () {
    $this->get(route('privacy.edit'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('privacy.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/Privacy')
            ->where('exports', [])
            ->where('retentionDays', DataExportStorage::RETENTION_DAYS)
            ->where('canDelete', true));
});

test('archyvo užsakymas įdeda job\'ą į eilę ir parodo pranešimą', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('privacy.export'))
        ->assertRedirect(route('privacy.edit'))
        ->assertInertiaFlash('toast.message', __('privacy.export.queued'));

    Queue::assertPushed(GenerateUserDataExport::class, fn (GenerateUserDataExport $job) => $job->user->is($user));
});

test('archyve – paskyra, užklausos, pokalbiai, atsiliepimai, mokėjimai, pranešimai ir nuotraukos', function () {
    Notification::fake();
    $client = User::factory()->create(['first_name' => 'Rūta', 'phone' => '+37061111111']);
    $client->addMedia(UploadedFile::fake()->image('avataras.jpg', 300, 300))->toMediaCollection('avatar');
    $request = ServiceRequest::factory()->create(['client_id' => $client->id, 'title' => 'Vonios remontas', 'address' => 'Gedimino pr. 1']);
    $offer = Offer::factory()->for($request)->create();
    // Factory pats prijungia dalyvius: užklausos klientą ir pasiūlymo teikėją
    $conversation = Conversation::factory()->create(['offer_id' => $offer->id]);
    Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $client->id, 'body' => 'Kada galite atvykti?']);
    Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $offer->providerProfile->user_id, 'body' => 'Rytoj.']);
    Review::factory()->create(['author_id' => $client->id, 'comment' => 'Puikiai atlikta']);
    Payment::factory()->create(['user_id' => $client->id, 'amount_cents' => 2490]);

    [$files, $data] = runExport($client);

    expect($files)->toContain('duomenys.json', 'README.txt')
        ->and(collect($files)->first(fn (string $name) => str_starts_with($name, 'nuotraukos/avatar/')))->not->toBeNull()
        ->and($data['paskyra'])->toMatchArray(['vardas' => 'Rūta', 'el_pastas' => $client->email, 'telefonas' => '+37061111111'])
        ->and($data['paskyra'])->not->toHaveKey('password')
        ->and($data['uzklausos'][0])->toMatchArray(['title' => 'Vonios remontas', 'address' => 'Gedimino pr. 1'])
        ->and($data['pokalbiai'][0]['zinutes'])->toHaveCount(2)
        ->and($data['pokalbiai'][0]['zinutes'][0])->toMatchArray(['autorius' => 'Jūs', 'tekstas' => 'Kada galite atvykti?'])
        ->and($data['pokalbiai'][0]['zinutes'][1]['autorius'])->toBe($offer->providerProfile->user->public_name)
        ->and($data['atsiliepimai']['parasyti'][0]['comment'])->toBe('Puikiai atlikta')
        ->and($data['mokejimai'][0]['amount_cents'])->toBe(2490)
        ->and($data['teikejo_profilis'])->toBeNull();

    Notification::assertSentTo($client, DataExportReady::class);
});

test('teikėjo archyve – profilis, paslaugos, pasiūlymai ir kreditų istorija', function () {
    $provider = ProviderProfile::factory()->create(['display_name' => 'Meistras Jonas']);
    app(CreditLedger::class)->credit($provider, 7, CreditTransactionType::Bonus, description: 'Sveikinimo dovana');
    Offer::factory()->for($provider)->create(['message' => 'Galiu atlikti per dvi dienas.']);

    [, $data] = runExport($provider->user);

    expect($data['teikejo_profilis'])->toMatchArray(['pavadinimas' => 'Meistras Jonas', 'kreditu_likutis' => 7])
        ->and($data['pasiulymai'][0]['message'])->toBe('Galiu atlikti per dvi dienas.')
        ->and($data['kreditu_operacijos'][0])->toMatchArray(['amount' => 7, 'description' => 'Sveikinimo dovana']);
});

test('atsisiųsti galima tik savo archyvą', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $file = '20261003-120000-abcdefabcdef.zip';
    Storage::disk('local')->put("data-exports/{$owner->id}/{$file}", 'zip turinys');

    $this->actingAs($owner)->get(route('privacy.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('exports.0.file', $file)
            // Galiojimas – 7 d. po sukūrimo (ir sukūrimo data nepasikeičia)
            ->where('exports.0.expires_at', fn (string $expires) => now()->addDays(7)->isSameDay($expires))
            ->where('exports.0.created_at', fn (string $created) => now()->isSameDay($created)));

    $this->actingAs($owner)
        ->get(route('privacy.download', ['file' => $file]))
        ->assertOk()
        ->assertDownload('duomenys-'.$file);

    // Kitas vartotojas su tuo pačiu failo vardu ieško SAVO kataloge – 404
    $this->actingAs($other)->get(route('privacy.download', ['file' => $file]))->assertNotFound();
    // Kitoks vardas (pvz. ../) maršrutas net neatitinka
    $this->actingAs($owner)->get('/settings/privacy/export/..%2F..%2F.env')->assertNotFound();
});

test('archyvo užsakymas ribojamas: ne daugiau 3 kartų per valandą', function () {
    Queue::fake();
    $user = User::factory()->create();

    foreach (range(1, 3) as $attempt) {
        $this->actingAs($user)->post(route('privacy.export'))->assertRedirect();
    }

    $this->actingAs($user)->post(route('privacy.export'))->assertTooManyRequests();
});

test('seni archyvai ištrinami komanda privacy:prune-exports', function () {
    $user = User::factory()->create();
    $storage = app(DataExportStorage::class);
    Storage::disk('local')->put("data-exports/{$user->id}/old.zip", 'x');
    touch(Storage::disk('local')->path("data-exports/{$user->id}/old.zip"), now()->subDays(8)->getTimestamp());
    Storage::disk('local')->put("data-exports/{$user->id}/new.zip", 'x');

    $this->artisan('privacy:prune-exports')->expectsOutputToContain('Ištrinta archyvų: 1')->assertSuccessful();

    expect(Storage::disk('local')->allFiles("data-exports/{$user->id}"))->toBe(["data-exports/{$user->id}/new.zip"])
        ->and($storage->list($user))->toBe([]); // „new.zip" neatitinka archyvo vardo formato
});

test('ištrintam vartotojui laukiantis job\'as tiesiog nevykdomas', function () {
    expect((new ReflectionClass(GenerateUserDataExport::class))->getProperty('deleteWhenMissingModels')->getDefaultValue())->toBeTrue();
});

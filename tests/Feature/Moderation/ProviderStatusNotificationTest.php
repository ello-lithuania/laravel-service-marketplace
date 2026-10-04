<?php

use App\Actions\Moderation\BanUser;
use App\Actions\Moderation\ChangeProviderStatus;
use App\Actions\Moderation\UnbanUser;
use App\Enums\ProviderStatus;
use App\Filament\Resources\ProviderProfiles\Pages\ListProviderProfiles;
use App\Filament\Resources\ProviderProfiles\Pages\ViewProviderProfile;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Category;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderStatusChanged;
use App\Notifications\ProviderVerified;
use App\Support\NotificationSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Etapas 9c: teikėjas gauna pranešimą, kai administratorius pakeičia jo profilio būseną.
 * Filament veiksmai testuojami per Livewire::test() (pest-plugin-livewire neįdiegtas).
 */
beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

/**
 * Užpildytas (kategorija + zona) profilis – tokį galima ir aktyvuoti.
 */
function moderatedProfile(ProviderStatus $status = ProviderStatus::Active): ProviderProfile
{
    $profile = ProviderProfile::factory()->create(['status' => $status, 'display_name' => 'Jonas Meistras']);
    $profile->categories()->attach(Category::factory()->leaf()->create());
    $profile->serviceAreas()->attach($profile->city_id);

    return $profile;
}

test('paslėpus su priežastimi teikėjas gauna pranešimą el. paštu ir varpelyje', function () {
    Notification::fake();
    $profile = moderatedProfile();

    Livewire::test(ListProviderProfiles::class)
        ->set('activeTab', 'all')
        ->callAction(TestAction::make('hide')->table($profile), ['reason' => '  Netikri atliktų darbų nuotraukos  '])
        ->assertHasNoActionErrors();

    expect($profile->refresh()->status)->toBe(ProviderStatus::Hidden);

    Notification::assertSentTo($profile->user, ProviderStatusChanged::class, function (ProviderStatusChanged $notification, array $channels) {
        return $notification->status === ProviderStatus::Hidden
            && $notification->reason === 'Netikri atliktų darbų nuotraukos'
            && $channels === ['mail', 'database'];
    });
    Notification::assertCount(1);
});

test('užblokuoti be priežasties ir vėl aktyvuoti – po pranešimą kiekvienam veiksmui', function () {
    Notification::fake();
    $profile = moderatedProfile();

    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->callAction('suspend');
    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->callAction('activate');

    Notification::assertSentTo($profile->user, ProviderStatusChanged::class,
        fn (ProviderStatusChanged $notification) => $notification->status === ProviderStatus::Suspended && $notification->reason === null);
    Notification::assertSentTo($profile->user, ProviderStatusChanged::class,
        fn (ProviderStatusChanged $notification) => $notification->status === ProviderStatus::Active);
    Notification::assertCount(2);
});

test('nepavykęs perėjimas pranešimo nesiunčia', function () {
    Notification::fake();
    $incomplete = ProviderProfile::factory()->suspended()->create();

    Livewire::test(ViewProviderProfile::class, ['record' => $incomplete->getRouteKey()])
        ->callAction('activate')
        ->assertNotified(__('moderation.provider_status.incomplete'));

    Notification::assertNothingSent();
});

test('„Patikrintas" – ProviderVerified tik pažymint, nuimant – nieko', function () {
    Notification::fake();
    $profile = moderatedProfile();

    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->callAction('verify');
    Notification::assertSentTo($profile->user, ProviderVerified::class);

    Livewire::test(ViewProviderProfile::class, ['record' => $profile->getRouteKey()])
        ->callAction('unverify');
    Notification::assertCount(1);
});

test('pranešimas neišjungiamas nustatymuose; nepatvirtintam el. paštui – tik varpelis', function () {
    $profile = moderatedProfile();
    $user = $profile->user;
    $user->forceFill([
        'notification_settings' => array_map(fn () => ['mail' => false, 'database' => false], NotificationSettings::defaults()),
    ])->save();

    expect((new ProviderStatusChanged($profile, ProviderStatus::Hidden))->via($user))->toBe(['mail', 'database'])
        ->and((new ProviderVerified($profile))->via($user))->toBe(['mail', 'database']);

    $user->forceFill(['email_verified_at' => null])->save();

    expect((new ProviderStatusChanged($profile, ProviderStatus::Hidden))->via($user))->toBe(['database']);
});

test('užblokuotai paskyrai ir anonimizuotam vartotojui pranešimas nesiunčiamas', function () {
    Notification::fake();
    $banned = moderatedProfile(ProviderStatus::Suspended);
    $banned->user->forceFill(['banned_at' => now()])->save();

    app(ChangeProviderStatus::class)->handle($banned, ProviderStatus::Hidden, 'Šlamštas');

    $deleted = moderatedProfile();
    $deleted->user->delete();
    app(ChangeProviderStatus::class)->handle($deleted->refresh(), ProviderStatus::Hidden);

    expect($deleted->status)->toBe(ProviderStatus::Hidden);
    Notification::assertNothingSent();
});

test('blokuojant paskyrą pranešimo nėra, o atblokavus su profiliu – „vėl aktyvus" arba „užbaikite pildymą"', function () {
    Notification::fake();
    $complete = moderatedProfile();
    $incomplete = ProviderProfile::factory()->create();

    app(BanUser::class)->handle($complete->user, $this->admin, 'Taisyklių pažeidimas');
    app(BanUser::class)->handle($incomplete->user, $this->admin, 'Taisyklių pažeidimas');
    Notification::assertNothingSent();

    Livewire::test(ViewUser::class, ['record' => $complete->user->getRouteKey()])
        ->callAction('unban', ['restore_profile' => true]);
    app(UnbanUser::class)->handle($incomplete->user->refresh());

    Notification::assertSentTo($complete->user, ProviderStatusChanged::class,
        fn (ProviderStatusChanged $notification) => $notification->status === ProviderStatus::Active);
    Notification::assertSentTo($incomplete->user, ProviderStatusChanged::class,
        fn (ProviderStatusChanged $notification) => $notification->status === ProviderStatus::Pending);
});

test('atblokavus be profilio atkūrimo pranešimo nėra', function () {
    Notification::fake();
    $profile = moderatedProfile();
    app(BanUser::class)->handle($profile->user, $this->admin, 'Taisyklių pažeidimas');

    app(UnbanUser::class)->handle($profile->user->refresh(), restoreProfile: false);

    expect($profile->refresh()->status)->toBe(ProviderStatus::Suspended);
    Notification::assertNothingSent();
});

test('laiško ir varpelio tekstai lietuviškai, mygtukas veda pagal būseną', function (ProviderStatus $status, ?string $reason, string $subject, string $action, Closure $url) {
    $profile = moderatedProfile($status);
    $notification = new ProviderStatusChanged($profile, $status, $reason);
    $mail = $notification->toMail($profile->user);
    $lines = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

    expect($mail->subject)->toBe($subject)
        ->and($mail->greeting)->toBe('Sveiki!')
        ->and($lines)->toContain('„Jonas Meistras"')
        ->and($mail->actionText)->toBe($action)
        ->and($mail->actionUrl)->toBe($url($profile));

    if ($reason !== null) {
        expect($lines)->toContain('Priežastis: '.$reason)
            ->and($notification->toArray($profile->user)['message'])->toEndWith('Priežastis: '.$reason);
    }

    expect($notification->toArray($profile->user))->toMatchArray([
        'provider_profile_id' => $profile->id,
        'status' => $status->value,
        'reason' => $reason,
    ]);
})->with([
    'paslėptas' => [ProviderStatus::Hidden, 'Dvigubas profilis', 'Jūsų profilis paslėptas', 'Atidaryti paskyrą', fn () => route('dashboard')],
    'užblokuotas' => [ProviderStatus::Suspended, null, 'Jūsų profilis užblokuotas', 'Atidaryti paskyrą', fn () => route('dashboard')],
    'aktyvus' => [ProviderStatus::Active, null, 'Jūsų profilis vėl aktyvus', 'Peržiūrėti profilį', fn (ProviderProfile $profile) => route('providers.show', $profile->slug)],
    'nebaigtas' => [ProviderStatus::Pending, null, 'Jūsų profilis atkurtas', 'Užbaigti profilį', fn () => route('provider.wizard')],
]);

test('ProviderVerified laiškas ir varpelio tekstas', function () {
    $profile = moderatedProfile();
    $notification = new ProviderVerified($profile);
    $mail = $notification->toMail($profile->user);

    expect($mail->subject)->toBe('Jūsų profilis patikrintas')
        ->and($mail->actionUrl)->toBe(route('providers.show', $profile->slug))
        ->and($notification->toArray($profile->user))->toBe([
            'provider_profile_id' => $profile->id,
            'message' => 'Jūsų profilis patikrintas – rodomas ženklelis „Patikrintas".',
        ]);
});

test('paspaudus pranešimą varpelyje – pagal dabartinę profilio būseną', function () {
    $profile = moderatedProfile(ProviderStatus::Hidden);
    $user = $profile->user;
    $open = function (string $type) use ($user, $profile) {
        $id = (string) Str::uuid();
        $user->notifications()->create([
            'id' => $id,
            'type' => 'App\\Notifications\\'.$type,
            'data' => ['provider_profile_id' => $profile->id, 'message' => 'Tekstas'],
        ]);

        return $this->actingAs($user)->get(route('notifications.open', $id));
    };

    $open('ProviderStatusChanged')->assertRedirect(route('dashboard'));

    $profile->forceFill(['status' => ProviderStatus::Active])->save();
    $open('ProviderStatusChanged')->assertRedirect(route('providers.show', $profile->slug));
    $open('ProviderVerified')->assertRedirect(route('providers.show', $profile->slug));

    $profile->delete();
    $open('ProviderVerified')->assertRedirect(route('dashboard'));
});

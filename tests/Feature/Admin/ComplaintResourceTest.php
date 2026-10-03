<?php

use App\Actions\Complaints\CloseComplaint;
use App\Actions\Complaints\TakeComplaintInReview;
use App\Enums\ComplaintStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\ComplaintAlreadyHandledException;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Models\Complaint;
use App\Models\Message;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ComplaintResolved;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\Messaging;

/**
 * Filament: skundų eilė ir nagrinėjimas (Etapas 6).
 */
beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('eilė: nauji ir nagrinėjami – seniausi viršuje; užbaigti – atskirame skirtuke', function () {
    $newer = Complaint::factory()->create(['created_at' => now()->subHour()]);
    $older = Complaint::factory()->create(['created_at' => now()->subDays(2)]);
    $inReview = Complaint::factory()->create(['status' => ComplaintStatus::InReview, 'created_at' => now()->subDays(5)]);
    $resolved = Complaint::factory()->resolved()->create();

    $this->get('/admin/skundai')->assertOk()->assertSee('Skundai');

    Livewire::test(ListComplaints::class)
        ->assertCanSeeTableRecords([$older, $newer, $inReview], inOrder: true)
        ->assertCanNotSeeTableRecords([$resolved])
        ->set('activeTab', 'closed')
        ->assertCanSeeTableRecords([$resolved])
        ->assertCanNotSeeTableRecords([$older]);
});

test('„Imti nagrinėti": in_review ir handled_by – šis administratorius', function () {
    $complaint = Complaint::factory()->create();

    Livewire::test(ListComplaints::class)
        ->callAction(TestAction::make('takeInReview')->table($complaint))
        ->assertNotified('Skundas paimtas nagrinėti');

    expect($complaint->refresh())
        ->status->toBe(ComplaintStatus::InReview)
        ->handled_by_id->toBe($this->admin->id);

    Livewire::test(ListComplaints::class)
        ->set('activeTab', 'mine')
        ->assertCanSeeTableRecords([$complaint]);
});

test('išsprendus su paslėpimu: atsiliepimas paslepiamas, reitingas perskaičiuojamas, pranešėjui pranešama', function () {
    $provider = ProviderProfile::factory()->create();
    Review::factory()->for($provider)->create(['rating' => 5]);
    $fake = Review::factory()->for($provider)->create(['rating' => 1]);
    $complaint = Complaint::factory()->create(['reportable_id' => $fake->id, 'reporter_id' => $provider->user_id]);
    expect((float) $provider->fresh()->rating_avg)->toBe(3.0);

    Livewire::test(ViewComplaint::class, ['record' => $complaint->getRouteKey()])
        ->callAction('resolve', ['note' => 'Atsiliepimas netikras – paslėptas.', 'hide_content' => true])
        ->assertHasNoActionErrors()
        ->assertNotified('Skundas išspręstas, pranešėjas informuotas');

    expect($complaint->refresh())
        ->status->toBe(ComplaintStatus::Resolved)
        ->handled_by_id->toBe($this->admin->id)
        ->resolution_note->toBe('Atsiliepimas netikras – paslėptas.')
        ->resolved_at->not->toBeNull()
        ->and($fake->refresh()->status)->toBe(ReviewStatus::Hidden)
        ->and((float) $provider->fresh()->rating_avg)->toBe(5.0);

    Notification::assertSentTo($provider->user, ComplaintResolved::class, function (ComplaintResolved $n) use ($provider) {
        $mail = $n->toMail($provider->user);

        return $n->toArray($provider->user)['message'] === 'Jūsų pranešimas apie „atsiliepimas" išnagrinėtas: pažeidimas patvirtintas, imtasi veiksmų'
            && in_array('Administratoriaus komentaras: Atsiliepimas netikras – paslėptas.', $mail->introLines, true);
    });
});

test('išsprendus skundą dėl žinutės – žinutė paslepiama (soft delete)', function () {
    $offer = Messaging::offer();
    $message = Messaging::send(Messaging::conversation($offer), Messaging::provider($offer), 'Mokėkite avansą');
    $complaint = Complaint::factory()->create([
        'reportable_type' => 'message',
        'reportable_id' => $message->id,
        'reporter_id' => Messaging::client($offer)->id,
    ]);

    Livewire::test(ViewComplaint::class, ['record' => $complaint->getRouteKey()])
        ->assertSee('Mokėkite avansą')
        ->callAction('resolve', ['note' => 'Sukčiavimo bandymas.', 'hide_content' => true]);

    expect(Message::query()->find($message->id))->toBeNull()
        ->and(Message::withTrashed()->find($message->id)?->trashed())->toBeTrue();

    // Peržiūroje paslėpta žinutė vis tiek matoma administratoriui (constrain + withTrashed)
    $this->get("/admin/skundai/{$complaint->id}")->assertOk()->assertSee('Žinutė (jau paslėpta)');
});

test('atmetus – būsena, paaiškinimas ir pranešimas „pažeidimo nerasta"; turinys nekeičiamas', function () {
    $review = Review::factory()->create();
    $complaint = Complaint::factory()->create(['reportable_id' => $review->id]);

    Livewire::test(ListComplaints::class)
        ->callAction(TestAction::make('reject')->table($complaint), ['note' => ''])
        ->assertHasActionErrors(['note' => 'required']);

    Livewire::test(ListComplaints::class)
        ->callAction(TestAction::make('reject')->table($complaint), ['note' => 'Pažeidimo nėra.'])
        ->assertHasNoActionErrors();

    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Rejected)
        ->and($review->refresh()->status)->toBe(ReviewStatus::Published);
    Notification::assertSentTo($complaint->reporter, ComplaintResolved::class, fn (ComplaintResolved $n) => str_ends_with(
        $n->toArray($complaint->reporter)['message'],
        'pažeidimo nerasta',
    ));
});

test('jau užbaigto skundo antrą kartą užbaigti negalima', function () {
    $complaint = Complaint::factory()->rejected()->create();

    // UI veiksmų nebėra…
    Livewire::test(ViewComplaint::class, ['record' => $complaint->getRouteKey()])
        ->assertActionHidden('resolve')
        ->assertActionHidden('takeInReview');

    // …o jei kitas administratorius užbaigė tarp puslapio atidarymo ir paspaudimo – Action tai pastebi (lockForUpdate)
    expect(fn () => app(CloseComplaint::class)->handle($complaint, $this->admin, ComplaintStatus::Resolved, 'Vėluojam'))
        ->toThrow(ComplaintAlreadyHandledException::class, 'Šis skundas jau išnagrinėtas.')
        ->and(fn () => app(TakeComplaintInReview::class)->handle($complaint, $this->admin))
        ->toThrow(ComplaintAlreadyHandledException::class);
    expect($complaint->refresh()->status)->toBe(ComplaintStatus::Rejected);
});

test('ComplaintResolved: nepatvirtintam el. paštui – tik varpelis', function () {
    $complaint = Complaint::factory()->rejected()->create();
    $unverified = User::factory()->unverified()->create();

    expect((new ComplaintResolved($complaint))->via($unverified))->toBe(['database'])
        ->and((new ComplaintResolved($complaint))->via($complaint->reporter))->toBe(['mail', 'database']);
});

test('ne administratorius skundų nemato', function () {
    $this->actingAs(User::factory()->create())->get('/admin/skundai')->assertForbidden();
});

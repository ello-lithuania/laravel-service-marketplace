<?php

namespace App\Notifications;

use App\Enums\ComplaintStatus;
use App\Enums\ReportableType;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Pranešėjui: jo skundas išnagrinėtas (išspręstas arba atmestas).
 *
 * Tai atsakymas į paties vartotojo veiksmą, todėl nustatymuose neišjungiamas (kaip slaptažodžio atkūrimo
 * laiškas): varpelis visada, laiškas – jei el. paštas patvirtintas.
 */
class ComplaintResolved extends BaseNotification
{
    public function __construct(public Complaint $complaint) {}

    /**
     * Grupės nustatymuose nėra – via() perrašytas žemiau.
     */
    public function settingsGroup(): string
    {
        return 'complaints';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->hasVerifiedEmail() ? ['database'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();
        $message = $this->mailMessage(__('complaints.notifications.resolved.subject'))
            ->line(__('complaints.notifications.resolved.intro', $replace))
            ->line(__('complaints.notifications.resolved.outcome', $replace));

        if ($this->complaint->resolution_note !== null && $this->complaint->resolution_note !== '') {
            $message->line(__('complaints.notifications.resolved.note', ['note' => $this->complaint->resolution_note]));
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'complaint_id' => $this->complaint->id,
            'message' => __('complaints.notifications.resolved.message', $this->replacements()),
        ];
    }

    /**
     * @return array{type: string, reason: string, outcome: string}
     */
    private function replacements(): array
    {
        $type = ReportableType::tryFrom($this->complaint->reportable_type);
        $outcome = $this->complaint->status === ComplaintStatus::Resolved ? 'resolved' : 'rejected';

        return [
            'type' => mb_strtolower($type?->label() ?? $this->complaint->reportable_type),
            'reason' => mb_strtolower($this->complaint->reason->label()),
            'outcome' => __('complaints.notifications.resolved.outcomes.'.$outcome),
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Teikėjui: paskelbtas naujas atsiliepimas apie jį (patvirtintas – iš karto, pagal pakvietimą – kai paskelbia
 * administratorius). data – kaip seed'uose: {review_id, message}.
 */
class NewReview extends BaseNotification
{
    public function __construct(public Review $review) {}

    public function settingsGroup(): string
    {
        return 'reviews';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $replace = $this->replacements();

        return $this->withSettingsHint($this->mailMessage(__('reviews.notifications.new_review.subject', $replace))
            ->line(__('reviews.notifications.new_review.intro', $replace))
            ->line('„'.Str::limit($this->review->comment, 300).'"')
            ->action(__('reviews.notifications.new_review.action'), route('provider-reviews.index')));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'review_id' => $this->review->id,
            'message' => __('reviews.notifications.new_review.message', $this->replacements()),
        ];
    }

    /**
     * @return array{rating: int, author: string}
     */
    private function replacements(): array
    {
        $author = $this->review->loadMissing('author')->author;

        return ['rating' => $this->review->rating, 'author' => $author->public_name ?? '–'];
    }
}

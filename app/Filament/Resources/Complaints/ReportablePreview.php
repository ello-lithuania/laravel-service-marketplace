<?php

namespace App\Filament\Resources\Complaints;

use App\Models\Complaint;
use App\Models\Message;
use App\Models\Offer;
use App\Models\ProviderProfile;
use App\Models\Review;
use App\Models\ServiceRequest;
use Illuminate\Support\Str;

/**
 * Skundžiamo turinio peržiūra administratoriui: ką skundžia, ištrauka ir nuoroda, kur jį pamatyti.
 * reportable – polimorfinis ryšys, todėl kiekvienam tipui – sava išvaizda (match pagal klasę).
 */
final class ReportablePreview
{
    /**
     * @return array{title: string, excerpt: string|null, url: string|null}
     */
    public static function for(Complaint $complaint): array
    {
        $item = $complaint->reportable;

        return match (true) {
            $item instanceof ServiceRequest => [
                'title' => $item->title,
                'excerpt' => Str::limit($item->description, 300),
                'url' => route('filament.admin.resources.uzklausos.view', $item->id),
            ],
            $item instanceof Offer => [
                'title' => 'Pasiūlymas užklausai #'.$item->service_request_id,
                'excerpt' => Str::limit($item->message, 300),
                'url' => route('filament.admin.resources.uzklausos.view', $item->service_request_id),
            ],
            $item instanceof Review => [
                'title' => str_repeat('★', $item->rating).' – '.$item->status->label(),
                'excerpt' => Str::limit($item->comment, 300),
                'url' => route('filament.admin.resources.atsiliepimai.view', $item->id),
            ],
            $item instanceof Message => [
                'title' => $item->trashed() ? 'Žinutė (jau paslėpta)' : 'Žinutė pokalbyje #'.$item->conversation_id,
                'excerpt' => Str::limit($item->body, 300),
                // Administratorius pokalbį gali skaityti (ConversationPolicy::view)
                'url' => route('conversations.show', $item->conversation_id),
            ],
            $item instanceof ProviderProfile => [
                'title' => $item->display_name,
                'excerpt' => $item->headline,
                'url' => route('providers.show', $item),
            ],
            default => ['title' => 'Turinys ištrintas', 'excerpt' => null, 'url' => null],
        };
    }
}

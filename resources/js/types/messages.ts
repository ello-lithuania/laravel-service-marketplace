// Etapas 6: pokalbių, žinučių, atsiliepimų ir skundų tipai (atitinka app/Http/Resources/Messages/* ir kt.)

import type {
    EnumValue,
    OfferStatus,
    ServiceRequestStatus,
} from './marketplace';

/** „Rašyti žinutę" mygtuko būsena pasiūlymui (App\Support\OfferMessaging) */
export type OfferMessaging = {
    conversation_id: number | null;
    can_start: boolean;
};

/** Kita pokalbio pusė: klientui – teikėjas, teikėjui – klientas, administratoriui – abu */
export type ConversationCounterpart = {
    name: string;
    role: 'provider' | 'client' | 'both';
};

export type ConversationListItem = {
    id: number;
    counterpart: ConversationCounterpart;
    title: string | null;
    offer_status: EnumValue<OfferStatus> | null;
    last_message: {
        excerpt: string;
        is_mine: boolean;
        created_at: string | null;
    } | null;
    unread_count: number;
    last_message_at: string | null;
};

export type ConversationDetail = {
    id: number;
    counterpart: ConversationCounterpart;
    service_request: {
        slug: string;
        title: string;
        status: EnumValue<ServiceRequestStatus>;
    } | null;
    offer: {
        id: number;
        status: EnumValue<OfferStatus>;
        price_cents: number | null;
        price_type: string;
    } | null;
};

/** Privataus failo (priedo, užklausos nuotraukos) nuoroda – per /failai/{id} su teisių patikra */
export type PrivateFile = {
    id: number;
    name: string;
    mime: string;
    size: number;
    is_image: boolean;
    thumb_url: string | null;
    url: string;
};

export type ChatMessage = {
    id: number;
    body: string | null;
    is_hidden: boolean;
    is_system: boolean;
    is_mine: boolean;
    sender_name: string;
    created_at: string | null;
    attachments: PrivateFile[];
};

// --- Atsiliepimai ---

export type ReviewStatus = 'pending' | 'published' | 'hidden';

/** Atsiliepimas paskyroje (App\Http\Resources\Reviews\AccountReviewResource) */
export type AccountReview = {
    id: number;
    rating: number;
    comment: string;
    status: EnumValue<ReviewStatus>;
    is_verified: boolean;
    author_name: string;
    service_request_title: string | null;
    created_at: string | null;
    published_at: string | null;
    provider_reply: string | null;
    provider_replied_at: string | null;
    can: { reply: boolean };
};

/** „Įvertinkite atliktą darbą" (App\Actions\Reviews\ListReviewPrompts) */
export type ReviewPrompt = {
    slug: string;
    title: string;
    provider: string | null;
    completed_at: string | null;
};

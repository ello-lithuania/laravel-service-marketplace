// Etapas 5: užklausų, pasiūlymų ir pranešimų tipai (atitinka app/Http/Resources/*)

/** PHP enum'as, išsiųstas kaip { value, label } – label jau lietuviškas */
export type EnumValue<T extends string = string> = {
    value: T;
    label: string;
};

export type ServiceRequestStatus =
    | 'pending'
    | 'open'
    | 'in_progress'
    | 'completed'
    | 'cancelled'
    | 'expired';

export type OfferStatus = 'pending' | 'accepted' | 'declined' | 'withdrawn';

export type Option = { id: number; name: string };

/** Kategorijų medis (užklausos formai): 3 lygiai, pasirenkamas tik lapas */
export type CategoryNode = {
    id: number;
    name: string;
    children: CategoryNode[];
};

export type ServiceRequestSummary = {
    id: number;
    slug: string;
    title: string;
    excerpt: string;
    status: EnumValue<ServiceRequestStatus>;
    category?: Option & { offer_cost_credits: number };
    city?: Option;
    budget_min_cents: number | null;
    budget_max_cents: number | null;
    start_preference: string;
    start_date: string | null;
    offers_count: number;
    published_at: string | null;
    expires_at: string | null;
    created_at: string | null;
};

export type ServiceRequestDetail = ServiceRequestSummary & {
    description: string;
    /** Tik klientui ir išrinktam teikėjui */
    address?: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    cancellation_reason: string | null;
    views_count: number;
};

export type OfferProvider = {
    id: number;
    display_name: string;
    slug: string;
    headline: string | null;
    rating_avg: number;
    reviews_count: number;
    completed_jobs_count: number;
    is_verified: boolean;
    city: string | null;
};

export type Offer = {
    id: number;
    status: EnumValue<OfferStatus>;
    message: string;
    price_cents: number | null;
    price_type: EnumValue;
    duration_text: string | null;
    start_date: string | null;
    credits_spent: number;
    viewed_at: string | null;
    responded_at: string | null;
    created_at: string | null;
    provider?: OfferProvider;
    service_request?: {
        id: number;
        slug: string;
        title: string;
        status: EnumValue<ServiceRequestStatus>;
    };
};

/** Laravel paginate() per API Resource collection */
export type Paginated<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        per_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
};

export type AppNotification = {
    id: string;
    /** Klasės vardas be namespace, pvz. „NewOffer" */
    type: string;
    message: string;
    read_at: string | null;
    created_at: string | null;
    /** Atidarymo nuoroda (pažymi perskaitytu ir nukreipia) */
    url: string;
};

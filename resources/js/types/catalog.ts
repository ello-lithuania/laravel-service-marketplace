// Katalogo (Etapas 4) puslapių props tipai. Atitinka PHP API Resources ir controller'ių masyvus.

export type CategoryLink = {
    id: number;
    name: string;
    slug: string;
};

export type CategoryWithChildren = CategoryLink & {
    children: CategoryLink[];
    /** Etapas 10: kategorijos puslapio 2 lygio grupės nuotrauka (jei įkelta) */
    image_url?: string | null;
};

/** 1 lygio kategorija su keliais 2 lygio pavyzdžiais (pradžios puslapis). */
export type RootCategory = CategoryWithChildren & {
    icon: string | null;
    /** Etapas 10: kategorijos nuotrauka (800×600), null – nuotraukos nėra */
    image_url: string | null;
};

/** Etapas 10: svetainės dizaino nuotrauka (SitePhotoKey); null – rodomas atsarginis dizainas. */
export type SitePhotoData = { url: string; alt: string | null } | null;

/** 1 lygio kategorija su visu pomedžiu (visų paslaugų puslapis). */
export type CategoryTreeRoot = CategoryLink & {
    icon: string | null;
    /** Etapas 10 */
    image_url: string | null;
    children: CategoryWithChildren[];
};

/** Etapas 10: platformos skaičiai pradžios puslapyje (SiteHighlights::stats, cache 1 val.). */
export type SiteStats = {
    providers: number;
    reviews: number;
    rating_avg: number | null;
    completed_jobs: number;
};

/** Etapas 10: 5★ atsiliepimas pradžios puslapyje (SiteHighlights::testimonials). */
export type Testimonial = {
    id: number;
    rating: number;
    comment: string;
    author_name: string;
    published_at: string | null;
    provider: { name: string; slug: string };
    category: string | null;
    city: string | null;
};

/** Etapas 10: bendri viešos dalies duomenys (HandleInertiaRequests „site", Inertia::once). */
export type SiteLayoutData = {
    categories: CategoryLink[];
    cities: CityOption[];
    auth_photo: SitePhotoData;
};

export type CityOption = {
    name: string;
    slug: string;
    name_locative: string;
    region?: string;
};

export type SortOption = {
    value: string;
    label: string;
};

/** Dabartiniai filtrai URL parametrų vardais (CatalogFilterRequest). */
export type CatalogFilters = {
    q: string | null;
    miestas: string | null;
    patikrinti: boolean;
    reitingas: number | null;
    rikiuoti: string;
};

export type SeoMeta = {
    title: string;
    description: string;
    canonical: string;
    robots: string;
    /** Etapas 8: schema.org JSON-LD (jau užkoduotas JSON tekstas) */
    json_ld: string | null;
};

export type PriceFrom = {
    cents: number;
    unit: string | null;
};

/** ProviderCardResource */
export type ProviderCard = {
    id: number;
    slug: string;
    display_name: string;
    headline: string | null;
    logo_url: string | null;
    city: string;
    serves_whole_country: boolean;
    is_verified: boolean;
    /** Etapas 9c: galiojanti prenumerata su ženkleliu (PlanBenefits::hasBadge) */
    has_pro_badge: boolean;
    rating_avg: number;
    reviews_count: number;
    completed_jobs_count: number;
    years_experience: number | null;
    categories: { name: string; slug: string }[];
    price_from: PriceFrom | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Laravel API Resource kolekcija su puslapiavimu: { data, links, meta }. */
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
        from: number | null;
        last_page: number;
        links: PaginationLink[];
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

/** ProviderProfileResource */
export type PublicProviderProfile = {
    id: number;
    slug: string;
    display_name: string;
    type: string;
    headline: string | null;
    description: string | null;
    website: string | null;
    logo_url: string | null;
    cover_url: string | null;
    city: { name: string; slug: string };
    serves_whole_country: boolean;
    service_areas: { name: string; slug: string }[];
    is_verified: boolean;
    /** Etapas 9c: galiojanti prenumerata su ženkleliu (PlanBenefits::hasBadge) */
    has_pro_badge: boolean;
    years_experience: number | null;
    rating_avg: number;
    reviews_count: number;
    completed_jobs_count: number;
    member_since: number | null;
    services: {
        name: string;
        slug: string | null;
        price_from_cents: number | null;
        price_unit: string | null;
    }[];
};

/** ReviewResource */
export type PublicReview = {
    id: number;
    rating: number;
    comment: string;
    author_name: string;
    is_verified: boolean;
    published_at: string | null;
    provider_reply: string | null;
    provider_replied_at: string | null;
};

/** PortfolioItemResource */
export type PortfolioItem = {
    id: number;
    title: string;
    description: string | null;
    completed_date: string | null;
    category: string | null;
    city: string | null;
    images: { url: string; thumb_url: string }[];
};

export type RatingBucket = {
    rating: number;
    count: number;
};

// Katalogo (Etapas 4) puslapių props tipai. Atitinka PHP API Resources ir controller'ių masyvus.

export type CategoryLink = {
    id: number;
    name: string;
    slug: string;
};

export type CategoryWithChildren = CategoryLink & {
    children: CategoryLink[];
};

/** 1 lygio kategorija su keliais 2 lygio pavyzdžiais (pradžios puslapis). */
export type RootCategory = CategoryWithChildren & {
    icon: string | null;
};

/** 1 lygio kategorija su visu pomedžiu (visų paslaugų puslapis). */
export type CategoryTreeRoot = CategoryLink & {
    icon: string | null;
    children: CategoryWithChildren[];
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
    city: { name: string; slug: string };
    serves_whole_country: boolean;
    service_areas: { name: string; slug: string }[];
    is_verified: boolean;
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

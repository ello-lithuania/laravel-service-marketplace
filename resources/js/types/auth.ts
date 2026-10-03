export type UserRole = 'client' | 'provider' | 'admin';

export type ProviderStatus = 'pending' | 'active' | 'hidden' | 'suspended';

/** Teikėjo profilio santrauka (App\Http\Resources\AuthUserResource) */
export type AuthProviderProfile = {
    id: number;
    slug: string;
    status: ProviderStatus;
    credits_balance: number;
};

/**
 * Prisijungęs vartotojas – tik laukai, kuriuos siunčia AuthUserResource.
 * Daugiau laukų čia nėra ir neturi būti: Inertia props mato naršyklė.
 */
export type User = {
    id: number;
    first_name: string;
    last_name: string;
    /** Skaičiuojamas laukas (accessor) „Vardas Pavardė" */
    name: string;
    email: string;
    email_verified_at: string | null;
    role: UserRole;
    /** Avataro miniatiūros URL arba null */
    avatar: string | null;
    /** Tik teikėjui, kuris jau pradėjo profilio vedlį */
    provider_profile: AuthProviderProfile | null;
};

export type Auth = {
    user: User;
};

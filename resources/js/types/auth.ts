export type User = {
    id: number;
    role: 'client' | 'provider' | 'admin';
    first_name: string;
    last_name: string;
    /** Skaičiuojamas laukas (accessor) „Vardas Pavardė" */
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

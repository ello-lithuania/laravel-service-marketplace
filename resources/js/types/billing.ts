// Etapas 7: kreditų, prenumeratų ir mokėjimų tipai (atitinka app/Http/Resources/Billing/*)

import type { EnumValue } from './marketplace';

export type CreditPackage = {
    id: number;
    name: string;
    credits: number;
    bonus_credits: number;
    total_credits: number;
    price_cents: number;
    price_per_credit_cents: number | null;
};

export type SubscriptionPlan = {
    id: number;
    slug: string;
    name: string;
    description: string | null;
    price_cents: number;
    billing_period: { value: 'month' | 'year'; label: string; per: string };
    credits_per_period: number;
    price_per_credit_cents: number | null;
    /** Lietuviški punktai iš features JSON */
    features: string[];
};

export type SubscriptionStatus =
    | 'active'
    | 'cancelled'
    | 'past_due'
    | 'expired';

export type ProviderSubscription = {
    id: number;
    plan: {
        name: string;
        slug: string;
        credits_per_period: number;
        price_cents: number;
        period_label: string;
    };
    status: EnumValue<SubscriptionStatus>;
    starts_at: string;
    ends_at: string;
    auto_renew: boolean;
    is_scheduled: boolean;
    can_cancel: boolean;
};

export type CreditTransactionItem = {
    id: number;
    amount: number;
    balance_after: number;
    type: EnumValue;
    description: string | null;
    created_at: string;
};

export type PaymentStatus =
    | 'pending'
    | 'paid'
    | 'failed'
    | 'cancelled'
    | 'refunded';

export type PaymentItem = {
    uuid: string;
    description: string;
    amount_cents: number;
    currency: string;
    status: EnumValue<PaymentStatus>;
    gateway: EnumValue;
    is_renewal: boolean;
    invoice_number: string | null;
    created_at: string | null;
    paid_at: string | null;
    can: { pay: boolean; download_invoice: boolean };
};

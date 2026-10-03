// Etapas 7: mokėjimų ir prenumeratų būsenų spalvos, kreditų formatavimas.

import type { PaymentStatus, SubscriptionStatus } from '@/types/billing';
import { plural } from './marketplace';

/** Ženklelio spalvos pagal mokėjimo ar prenumeratos būseną (Tailwind klasės) */
export function billingStatusClasses(
    status: PaymentStatus | SubscriptionStatus,
): string {
    switch (status) {
        case 'paid':
        case 'active':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300';
        case 'pending':
        case 'cancelled':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
        case 'failed':
        case 'past_due':
            return 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

/** 1 kreditas, 2 kreditai, 10 kreditų */
export function formatCredits(count: number): string {
    return `${count} ${plural(Math.abs(count), 'kreditas', 'kreditai', 'kreditų')}`;
}

/** Ledger sumos ženklas: „+30", „−2" */
export function formatCreditChange(amount: number): string {
    return amount > 0 ? `+${amount}` : `−${Math.abs(amount)}`;
}

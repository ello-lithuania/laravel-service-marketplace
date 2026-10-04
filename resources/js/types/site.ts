// Etapas 10: svetainės informaciniai puslapiai (PhotoCredits service → PhotoCreditsController)

/** Viena nuotrauka puslapyje „Nuotraukų autoriai" (media custom_properties.credit). */
export type PhotoCreditItem = {
    id: number;
    thumb_url: string;
    /** Kur naudojama: kategorijos pavadinimas arba svetainės vieta (demo – null) */
    label: string | null;
    title: string | null;
    author: string | null;
    author_url: string | null;
    source: string;
    source_url: string | null;
    license: string;
    license_url: string | null;
};

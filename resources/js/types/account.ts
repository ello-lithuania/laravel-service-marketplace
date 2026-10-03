// Paskyros (teikėjo profilio vedlio, portfolio) puslapių tipai – atitinka controller'ių props.

/** Vedlio žingsnis (App\Enums\ProviderWizardStep::progress) */
export type WizardStep = {
    key: 'details' | 'categories' | 'areas' | 'prices';
    label: string;
    href: string;
    done: boolean;
    required: boolean;
    available: boolean;
};

/** Kategorijų medžio mazgas (App\Actions\ProviderProfile\BuildCategoryTree) */
export type CategoryNode = {
    id: number;
    name: string;
    children: CategoryNode[];
};

export type IdName = {
    id: number;
    name: string;
};

export type CityOption = IdName;

/** Apskritis su savivaldybėmis (App\Actions\ProviderProfile\ListRegionsWithCities) */
export type RegionOption = {
    id: number;
    name: string;
    cities: CityOption[];
};

export type SelectOption = {
    value: string;
    label: string;
};

export type PortfolioImage = {
    id: number;
    thumb: string;
    url: string;
};

/** Atliktas darbas redagavimo formai (PortfolioItemController::edit) */
export type PortfolioItemData = {
    id: number;
    title: string;
    description: string | null;
    category_id: number | null;
    city_id: number | null;
    completed_date: string | null;
    images: PortfolioImage[];
};

/** Atliktas darbas sąraše (PortfolioItemController::index) */
export type PortfolioListItem = {
    id: number;
    title: string;
    category: string | null;
    city: string | null;
    completed_date: string | null;
    images_count: number;
    cover: string | null;
};

/** Profilio pilnumo punktas (App\Actions\ProviderProfile\BuildProfileChecklist) */
export type ChecklistItem = {
    label: string;
    done: boolean;
    required: boolean;
    href: string | null;
};

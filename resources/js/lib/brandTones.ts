// Etapas 10: atsarginio dizaino (kai nėra nuotraukos) spalvų tonai.
// Kiekviena 1 lygio sritis turi savo toną pagal ikoną (sodas – samanų žalia, elektra – ochra, santechnika – žydra),
// todėl kortelės be nuotraukų neatrodo vienodos. 2–3 lygių kategorijos paveldi srities ikoną, taigi ir toną.

export type BrandTone = {
    /** tamsesnė (kairė viršus) ir šviesesnė (dešinė apačia) gradiento spalvos; baltas tekstas ant jų ≥ 4,5:1 */
    from: string;
    to: string;
};

export const brandTones = {
    pine: { from: '#0f5b45', to: '#1d7a5d' },
    teal: { from: '#0d5763', to: '#16808c' },
    moss: { from: '#41602a', to: '#668537' },
    clay: { from: '#8a3c22', to: '#b85a35' },
    ochre: { from: '#7d5210', to: '#b57d1d' },
    slate: { from: '#2f4357', to: '#4c6b88' },
    plum: { from: '#4f3358', to: '#784d84' },
    graphite: { from: '#252f35', to: '#46565f' },
    rose: { from: '#7a3348', to: '#a14f67' },
    indigo: { from: '#2b3a67', to: '#4a5d9a' },
    coral: { from: '#8b3434', to: '#bb5249' },
    cedar: { from: '#5a3822', to: '#87573a' },
} satisfies Record<string, BrandTone>;

export type BrandToneName = keyof typeof brandTones;

const toneByIcon: Record<string, BrandToneName> = {
    hammer: 'clay',
    wrench: 'teal',
    plug: 'ochre',
    sparkles: 'pine',
    truck: 'slate',
    trees: 'moss',
    sofa: 'plum',
    car: 'graphite',
    scissors: 'rose',
    laptop: 'indigo',
    'party-popper': 'coral',
    'graduation-cap': 'cedar',
};

const toneNames = Object.keys(brandTones) as BrandToneName[];

/**
 * Tonas kategorijai: pagal ikoną, o nežinomai ikonai (pvz. įvestai per Filament) – pagal pavadinimą/slug,
 * kad ta pati kategorija visur (pradžios puslapis, kategorijos juosta) būtų tos pačios spalvos.
 */
export function toneFor(icon: string | null, seed = ''): BrandTone {
    if (icon && toneByIcon[icon]) {
        return brandTones[toneByIcon[icon]];
    }

    let hash = 0;

    for (const char of seed) {
        hash = (hash * 31 + char.charCodeAt(0)) >>> 0;
    }

    return brandTones[toneNames[hash % toneNames.length]];
}

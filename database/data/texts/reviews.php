<?php

/**
 * Atsiliepimų bankas pagal įvertinimą (1–5) ir teikėjų atsakymai.
 */

return [
    'by_rating' => [
        5 => [
            'Puikiai atliktas darbas, labai rekomenduoju!',
            'Viskas atlikta greitai ir kokybiškai.',
            'Labai malonus bendravimas, darbas atliktas net geriau nei tikėjausi.',
            'Atvyko laiku, viską paaiškino, po darbo sutvarkė.',
            'Kreipsiuosi dar kartą, ačiū!',
            'Profesionalas, savo srities žinovas.',
        ],
        4 => [
            'Darbas atliktas gerai, tik šiek tiek vėlavo.',
            'Esu patenkintas, nors kaina galėjo būti mažesnė.',
            'Viskas gerai, rekomenduoju.',
            'Kokybė gera, bendravimas galėtų būti aktyvesnis.',
        ],
        3 => [
            'Darbas atliktas, bet su keliomis pastabomis.',
            'Vidutiniškai, tikėjausi daugiau.',
            'Teko kelis kartus priminti, bet galiausiai viskas padaryta.',
        ],
        2 => [
            'Vėlavo, darbą teko taisyti.',
            'Kokybė neatitiko lūkesčių.',
            'Kaina išaugo darbų eigoje, apie tai nebuvo susitarta.',
        ],
        1 => [
            'Vėlavo dvi dienas, darbas liko nebaigtas.',
            'Nerekomenduoju, į skambučius nebeatsako.',
            'Darbas atliktas labai prastai.',
        ],
    ],
    'replies' => [
        'Ačiū už atsiliepimą!',
        'Dėkojame, buvo malonu dirbti.',
        'Atsiprašome už nepatogumus, susisieksime ir viską išspręsime.',
        'Ačiū, kad pasirinkote mus.',
    ],
];

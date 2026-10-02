<?php

// Cuentas OFICIALES reales de los clubes en redes (verificadas una a una).
// El juego muestra el @ y los seguidores reales en "Redes del club".
// Los filiales usan la cuenta de su cantera (Academia VCF, La Fábrica...).
// Equipos sin entrada usan el handle generado automáticamente.
//
// followers: cifra aproximada (se redondea al mostrarla). Cuando no se
// conoce, se omite y el juego usa la estimación por reputación.

return [
    'ES' => [
        // Liga F — cuentas oficiales verificadas
        'Real Madrid CF' => ['handle' => '@realmadridfem', 'followers' => 1200000],
        'Sevilla FC' => ['handle' => '@SevillaFC_Fem', 'followers' => 32000],
        'FC Badalona Women' => ['handle' => '@fcbadalonawomen'],
        'Logroño United' => ['handle' => '@LogronoUnited'],
        'Madrid CFF' => ['handle' => '@MadridCFF'],
        'Atlético de Madrid' => ['handle' => '@AtletiFemenino'],
        'FC Barcelona' => ['handle' => '@FCBfemeni', 'followers' => 1200000],
        // El Eibar no tiene cuenta femenina separada: se usa la del club.
        'SD Eibar' => ['handle' => '@SDEibar', 'followers' => 241000],
        'Real Sociedad' => ['handle' => '@RealSociedadFEM', 'followers' => 19700],
        'Valencia CF Femenino' => ['handle' => '@VCF_Femenino', 'followers' => 44500],
        'Costa Adeje Tenerife Egatesa' => ['handle' => '@CDTFemenino'],
        'Athletic Club' => ['handle' => '@AthleticClubFem', 'followers' => 19000],
        'Granada CF' => ['handle' => '@GranadaCF_Fem'],
        'RCD Espanyol' => ['handle' => '@RCDEFemeni', 'followers' => 13900],
        'Deportivo Alavés' => ['handle' => '@AlavesFem'],
        'Dépor Abanca' => ['handle' => '@RCDeportivoFem', 'followers' => 8000],

        // Filiales — cuentas de la cantera / academia del club.
        // Ningún filial femenino tiene cuenta propia: se usa la academia.
        'Real Madrid B' => ['handle' => '@lafabricacrm', 'followers' => 1100000],
        'FC Barcelona B' => ['handle' => '@FCBmasia'],
        'FC Barcelona C' => ['handle' => '@FCBmasia'],
        'Valencia CF B' => ['handle' => '@Academia_VCF', 'followers' => 24000],
        'Atlético de Madrid B' => ['handle' => '@AtletiAcademia'],
        'RCD Espanyol B' => ['handle' => '@RCDE_La21'],
        'Real Sociedad B' => ['handle' => '@RSZubieta_', 'followers' => 5000],
        'Dépor ABANCA B' => ['handle' => '@DeporCanteira'],
        'Granada CF B' => ['handle' => '@CanteraNazari'],
        // Sin cuenta de cantera conocida (Lezama, Alavés, Eibar B, etc.):
        // usan el handle generado automáticamente.
    ],
];

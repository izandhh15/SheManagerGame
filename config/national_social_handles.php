<?php

// Cuentas OFICIALES reales de las selecciones femeninas en redes
// (verificadas una a una el 02-10-2026; PROHIBIDO inventar).
// El juego muestra el @ en "Redes de la selección".
//
// 'federation' => true marca que es la cuenta de la federación (no hay
// cuenta femenina separada): la UI lo indica para no engañar.
// Selecciones sin entrada usan el handle generado automáticamente.

return [
    // Cuentas femeninas específicas verificadas (X e IG)
    'Spain' => ['x' => '@SEFutbolFem', 'instagram' => '@sefutbolfem'],
    'England' => ['x' => '@Lionesses', 'instagram' => '@lionesses'],
    'United States' => ['x' => '@USWNT', 'instagram' => '@uswnt'],
    'Australia' => ['x' => '@TheMatildas', 'instagram' => '@matildas'],
    'Brazil' => ['x' => '@SelecaoFeminina', 'instagram' => '@selecaofemininadefutebol'],
    'Germany' => ['x' => '@DFB_Frauen', 'instagram' => '@dfb_frauenteam'],
    'France' => ['x' => '@equipedefrancef', 'instagram' => '@equipedefrancef'],
    'Mexico' => ['x' => '@Miseleccionfem'],

    // X femenina verificada (IG sin verificar: no se usa)
    'Italy' => ['x' => '@AzzurreFIGC'],
    'Belgium' => ['x' => '@BelRedFlames'],
    'China' => ['x' => '@CHNWNT'],
    'Nigeria' => ['x' => '@NGSuper_Falcons'],
    'South Africa' => ['x' => '@Banyana_Banyana'],

    // Mixtas: femenina en una red, federación en la otra
    'Netherlands' => ['x' => '@KNVB', 'federation' => true, 'instagram' => '@oranjeleeuwinnen'],
    'Japan' => ['x' => '@jfa_nadeshiko', 'instagram' => '@japanfootballassociation', 'instagram_federation' => true],

    // Solo federación (sin cuenta femenina separada)
    'Portugal' => ['x' => '@selecaoportugal', 'federation' => true],
    'Norway' => ['x' => '@nff_landslag', 'federation' => true],
    'Denmark' => ['x' => '@DBUfodbold', 'federation' => true],
    'Switzerland' => ['x' => '@nati_sfv_asf', 'federation' => true],
    'Austria' => ['x' => '@oefb1904', 'federation' => true],
    'Canada' => ['x' => '@CanadaSoccerEN', 'federation' => true],
    'Colombia' => ['x' => '@FCFSeleccionCol', 'instagram' => '@fcfseleccioncolombia', 'federation' => true],

    // Sin cuenta oficial verificada (Argentina, Corea del Sur, Marruecos,
    // Suecia, resto): handle generado automáticamente.
];

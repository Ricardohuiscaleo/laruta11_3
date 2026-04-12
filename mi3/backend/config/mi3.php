<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ciclos de Turnos 4x4
    |--------------------------------------------------------------------------
    | Configuración de los ciclos rotativos 4 días trabajo / 4 días libre.
    | Cada ciclo tiene una fecha base y dos personas que se alternan.
    */
    'shift_cycles' => [
        'seguridad' => [
            'base_date' => '2026-02-11',
            'person_a_id' => 5,   // Ricardo
            'person_b_id' => 10,  // Claudio
        ],
        'cajeros' => [
            'base_date' => '2026-02-02',
            'person_a_id' => 1,   // Camila
            'person_b_id' => 12,  // Dafne
        ],
        'plancheros' => [
            'base_date' => '2026-02-03',
            'person_a_id' => 3,   // Andres Aguilera
            'person_b_id' => 3,   // Andres Aguilera (trabaja todos los días)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IDs de personal para turnos dinámicos
    |--------------------------------------------------------------------------
    | IDs 1-4 son los trabajadores con turnos normales manuales que se filtran
    | para evitar duplicados con los turnos dinámicos generados.
    */
    'dynamic_shift_personal_ids' => [],

    /*
    |--------------------------------------------------------------------------
    | Crédito R11
    |--------------------------------------------------------------------------
    */
    'r11_credit' => [
        'auto_deduct_day' => 1,
        'auto_deduct_time' => '06:00',
        'reminder_day' => 28,
        'reminder_time' => '10:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gmail
    |--------------------------------------------------------------------------
    */
    'gmail' => [
        'sender_email' => env('GMAIL_SENDER_EMAIL', 'saboresdelaruta11@gmail.com'),
    ],
];

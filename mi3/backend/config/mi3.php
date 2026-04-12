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
            'person_a' => 'Ricardo',
            'person_b' => 'Claudio',
        ],
        'cajeros' => [
            'base_date' => '2026-02-02',
            'person_a' => 'Camila',
            'person_b' => 'Dafne',
        ],
        'plancheros' => [
            'base_date' => '2026-02-03',
            'person_a' => 'Andrés',
            'person_b' => 'Andrés',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IDs de personal para turnos dinámicos
    |--------------------------------------------------------------------------
    | IDs 1-4 son los trabajadores con turnos normales manuales que se filtran
    | para evitar duplicados con los turnos dinámicos generados.
    */
    'dynamic_shift_personal_ids' => [1, 2, 3, 4],

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

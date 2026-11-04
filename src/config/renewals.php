<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo interno de vencimientos
    |--------------------------------------------------------------------------
    |
    | Dirección interna a la que se copian los recordatorios de vencimiento.
    | Configurable con RENEWALS_NOTIFY_EMAIL.
    |
    */

    'notify_email' => env('RENEWALS_NOTIFY_EMAIL', 'renovaciones@wortev.com'),

    /*
    |--------------------------------------------------------------------------
    | Umbrales de recordatorio (días antes del vencimiento)
    |--------------------------------------------------------------------------
    |
    | Se envía un recordatorio cuando faltan exactamente estos días.
    |
    */

    'thresholds' => [30, 15, 7, 1],

];

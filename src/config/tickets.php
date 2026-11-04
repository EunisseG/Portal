<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Correo de notificaciones de tickets
    |--------------------------------------------------------------------------
    |
    | Dirección a la que se avisa cuando se abre o responde un ticket. Se puede
    | sobreescribir con la variable de entorno TICKETS_NOTIFY_EMAIL.
    |
    */

    'notify_email' => env('TICKETS_NOTIFY_EMAIL', 'ticket_infra@wortev.com'),

];

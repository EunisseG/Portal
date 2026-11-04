<?php

/*
| Datos del super administrador inicial que crea InitialUsersSeeder.
| Se leen del entorno para no dejar credenciales en el código.
*/
return [
    'name' => env('SUPERADMIN_NAME', 'Super administrador'),
    'email' => env('SUPERADMIN_EMAIL'),
    'password' => env('SUPERADMIN_PASSWORD'),
];

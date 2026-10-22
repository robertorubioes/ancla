<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cuenta de superadministrador
    |--------------------------------------------------------------------------
    |
    | Credenciales de la cuenta que crea SuperadminSeeder. Nunca se cablean en
    | el codigo: en produccion y staging SUPERADMIN_PASSWORD es obligatorio y
    | el seeder aborta si falta. En local, si no se define, se genera una
    | contrasena aleatoria y se muestra una sola vez por consola.
    |
    */

    'email' => env('SUPERADMIN_EMAIL'),

    'name' => env('SUPERADMIN_NAME', 'Superadmin'),

    'password' => env('SUPERADMIN_PASSWORD'),

];

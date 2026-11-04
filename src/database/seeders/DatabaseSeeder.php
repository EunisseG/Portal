<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Base imprescindible en cualquier entorno: roles/permisos y el
        // super administrador inicial (este último a partir del .env).
        $this->call([
            RolesAndPermissionsSeeder::class,
            InitialUsersSeeder::class,
            SlaPoliciesSeeder::class,
            InternalAreasSeeder::class,
            SurveyFormsSeeder::class,
        ]);

        // Datos de demostración: SOLO fuera de producción. En producción no se
        // siembran datos ficticios. Para forzarlos en desarrollo, ejecuta el
        // DatabaseSeeder en un entorno != production.
        if (! app()->environment('production')) {
            $this->call([
                DemoUsersSeeder::class,
                ClientsDemoSeeder::class,
                PlansDemoSeeder::class,
                ServicesDemoSeeder::class,
                InvoicesDemoSeeder::class,
                TicketsDemoSeeder::class,
                ContentDemoSeeder::class,
            ]);
        }
    }
}

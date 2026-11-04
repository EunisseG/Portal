<?php

namespace Database\Seeders;

use App\Domain\Internal\Models\InternalArea;
use Illuminate\Database\Seeder;

/**
 * Áreas internas iniciales. Son un punto de partida editable: el administrador
 * las ajusta con la estructura real de la empresa desde el panel.
 */
class InternalAreasSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            'Recursos Humanos',
            'Sistemas',
            'Finanzas',
            'Administración',
            'Soporte',
        ];

        foreach ($areas as $name) {
            InternalArea::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}

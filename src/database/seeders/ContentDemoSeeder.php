<?php

namespace Database\Seeders;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\KbArticle;
use App\Domain\Content\Models\KbCategory;
use App\Domain\Content\Models\News;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Contenido de ejemplo (desarrollo): noticias y base de conocimientos.
 */
class ContentDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Noticias.
        $noticias = [
            ['Mantenimiento programado de servidores', 'Realizaremos mantenimiento el próximo fin de semana.'],
            ['Nuevos planes de hosting disponibles', 'Lanzamos planes con más recursos al mismo precio.'],
            ['Mejoras de seguridad en el portal', 'Reforzamos la protección de tu cuenta.'],
        ];
        foreach ($noticias as [$title, $excerpt]) {
            News::create([
                'title' => $title,
                'slug' => Str::slug($title),
                'excerpt' => $excerpt,
                'body' => "## {$title}\n\n{$excerpt}\n\nGracias por confiar en WORTEV.",
                'status' => ContentStatus::Published->value,
                'published_at' => now()->subDays(rand(1, 20)),
            ]);
        }
        // Una en borrador (no visible al cliente).
        News::factory()->draft()->create(['title' => 'Borrador interno', 'slug' => 'borrador-interno']);

        // Base de conocimientos.
        $categorias = [
            'Primeros pasos' => [
                'Cómo acceder a tu portal',
                'Configura tu perfil',
            ],
            'Hosting' => [
                'Cómo consultar tus servicios',
                'Entiende las fechas de vencimiento',
            ],
            'Facturación' => [
                'Dónde ver tus facturas',
            ],
        ];

        foreach ($categorias as $catName => $articulos) {
            $category = KbCategory::create([
                'name' => $catName,
                'slug' => Str::slug($catName),
                'description' => 'Artículos sobre '.strtolower($catName).'.',
            ]);

            foreach ($articulos as $title) {
                KbArticle::create([
                    'kb_category_id' => $category->id,
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'excerpt' => $title.' — guía rápida.',
                    'body' => "## {$title}\n\nEn este artículo te explicamos **{$title}** paso a paso.\n\n1. Primer paso\n2. Segundo paso\n3. Listo.",
                    'status' => ContentStatus::Published->value,
                    'published_at' => now()->subDays(rand(1, 15)),
                ]);
            }
        }
    }
}

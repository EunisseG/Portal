<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita el catálogo editable de tipos de producto: el tipo pasa a ser un valor
 * fijo ("Funciona como": hosting, dominio, correo, licencia u otro) y toda la
 * organización queda en Categorías.
 *
 * Los productos con un tipo personalizado (p. ej. "SSL") pasan a "Otro" y, si
 * no tenían categoría, se les asigna una categoría con el nombre de ese tipo
 * para no perder cómo estaban agrupados.
 */
return new class extends Migration
{
    private const FIXED = ['hosting', 'domain', 'email', 'license', 'custom'];

    public function up(): void
    {
        $labels = Schema::hasTable('product_type_items')
            ? DB::table('product_type_items')->pluck('label', 'slug')->all()
            : [];

        $products = DB::table('products')
            ->where(fn ($q) => $q->whereNull('type')->orWhereNotIn('type', self::FIXED))
            ->get(['id', 'type', 'category_id']);

        foreach ($products as $product) {
            $update = ['type' => 'custom'];

            if ($product->category_id === null && ! empty($product->type)) {
                $title = $labels[$product->type] ?? ucfirst((string) $product->type);
                $categoryId = DB::table('product_categories')->where('title', $title)->value('id')
                    ?? DB::table('product_categories')->insertGetId([
                        'title' => $title, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                $update['category_id'] = $categoryId;
            }

            DB::table('products')->where('id', $product->id)->update($update);
        }

        Schema::dropIfExists('product_type_items');
    }

    public function down(): void
    {
        Schema::create('product_type_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('label');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('product_type_items')->insert([
            ['slug' => 'hosting', 'label' => 'Hosting', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'domain', 'label' => 'Dominio', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'email', 'label' => 'Correo', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'license', 'label' => 'Licencia', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'custom', 'label' => 'Otro', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};

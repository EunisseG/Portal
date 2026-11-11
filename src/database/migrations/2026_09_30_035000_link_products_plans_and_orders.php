<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza productos, planes y pedidos:
 *  - products.tax_rate: IVA del producto de pago único (los recurrentes lo
 *    llevan por ciclo en product_prices).
 *  - plans.category_id: categoría del plan, que se copia a su producto espejo
 *    para que aparezca en esa categoría al hacer un pedido.
 *  - services.product_id: producto del catálogo del que salió el pedido.
 *  - Tipo de producto "Dominio": el título del producto es la extensión
 *    (.com, .mx...) y se ofrece en el apartado de dominio del pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Por omisión 0: los productos de pago único que ya existían se
            // cobraban sin IVA y así se conservan hasta que el admin les asigne
            // su IVA (el formulario de productos propone 16% al crear).
            $table->decimal('tax_rate', 5, 2)->default(0)->after('price_cents');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('type')
                ->constrained('product_categories')->nullOnDelete();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('plan_id')
                ->constrained('products')->nullOnDelete();
        });

        if (! DB::table('product_type_items')->where('slug', 'domain')->exists()) {
            $now = now();
            DB::table('product_type_items')->insert([
                'slug' => 'domain', 'label' => 'Dominio', 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });

        DB::table('product_type_items')->where('slug', 'domain')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Producto espejo de un plan: cuando un producto proviene de un plan, guarda
 * su plan_id. Permite que los planes se reflejen en el catálogo de productos
 * sin capturarlos dos veces. Único por plan (un plan = un producto espejo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('id')
                ->constrained('plans')
                ->nullOnDelete();

            $table->unique('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['plan_id']);
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};

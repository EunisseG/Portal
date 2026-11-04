<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo reutilizable de complementos. A diferencia de la tabla `addons`
 * (que son instancias ligadas a un servicio concreto), aquí viven los
 * complementos "plantilla" que se pueden agregar a cualquier pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_catalog_items');
    }
};

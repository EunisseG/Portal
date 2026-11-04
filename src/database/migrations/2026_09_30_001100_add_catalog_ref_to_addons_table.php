<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referencia opcional del complemento de un servicio al ítem de catálogo del
 * que se creó. Permite trazabilidad; el precio queda "congelado" en el addon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')
                ->nullable()
                ->after('service_id')
                ->constrained('addon_catalog_items')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_item_id');
        });
    }
};

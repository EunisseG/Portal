<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca los complementos que representan el cobro de un dominio, para poder
 * sincronizarlos desde el formulario del pedido y distinguirlos en el desglose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->boolean('is_domain')->default(false)->after('catalog_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('is_domain');
        });
    }
};

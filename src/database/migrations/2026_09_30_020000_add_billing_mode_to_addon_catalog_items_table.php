<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_catalog_items', function (Blueprint $table) {
            // Modo de cobro: 'one_time' (precio plano) o 'recurring' (por ciclo).
            $table->string('billing_mode')->default('one_time')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('addon_catalog_items', function (Blueprint $table) {
            $table->dropColumn('billing_mode');
        });
    }
};

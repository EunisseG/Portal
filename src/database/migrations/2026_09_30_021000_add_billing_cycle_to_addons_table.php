<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            // Ciclo de facturación del complemento recurrente (null = pago único).
            // Es metadato: el precio ya queda congelado en price_cents.
            $table->string('billing_cycle')->nullable()->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn('billing_cycle');
        });
    }
};

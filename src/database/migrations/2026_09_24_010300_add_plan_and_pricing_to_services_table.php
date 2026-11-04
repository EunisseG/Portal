<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Referencia opcional al plan del catálogo del que salió el servicio.
            $table->foreignId('plan_id')->nullable()->after('server_id')->constrained()->nullOnDelete();
            // Desglose del precio del ciclo contratado (todo en centavos, salvo la tasa).
            $table->unsignedBigInteger('unit_price_cents')->default(0)->after('price_cents');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('unit_price_cents');
            $table->unsignedBigInteger('total_cents')->default(0)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['unit_price_cents', 'tax_rate', 'total_cents']);
        });
    }
};

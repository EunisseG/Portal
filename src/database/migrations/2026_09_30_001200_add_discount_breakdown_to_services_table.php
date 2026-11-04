<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desglose de precios del pedido (Service): subtotal (plan + complementos),
 * descuento aplicado (promoción) e impuesto, además de la referencia a la
 * promoción y una "foto" del descuento en el momento de aplicarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Promoción aplicada (catálogo). Si se borra la promoción, se
            // conserva el pedido pero se desliga.
            $table->foreignId('promotion_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('promotions')
                ->nullOnDelete();

            // Snapshot del descuento en el momento de aplicarlo (para que el
            // total no cambie si luego editan la promoción del catálogo).
            $table->string('discount_type')->nullable()->after('tax_rate');   // DiscountType
            $table->unsignedBigInteger('discount_value')->nullable()->after('discount_type');

            // Desglose calculado (centavos).
            $table->unsignedBigInteger('subtotal_cents')->default(0)->after('discount_value');
            $table->unsignedBigInteger('discount_cents')->default(0)->after('subtotal_cents');
            $table->unsignedBigInteger('tax_cents')->default(0)->after('discount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['discount_type', 'discount_value', 'subtotal_cents', 'discount_cents', 'tax_cents']);
        });
    }
};

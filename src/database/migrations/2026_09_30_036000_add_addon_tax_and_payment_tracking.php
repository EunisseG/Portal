<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * IVA propio de los complementos y rastreo de su pago:
 *  - addon_catalog_items.tax_rate: IVA del complemento de pago único (los
 *    recurrentes lo llevan por ciclo en addon_prices).
 *  - addons.tax_rate: IVA congelado al agregarlo al pedido (null = el del pedido).
 *  - addons.paid_at: cuándo se pagó; el cliente solo ve complementos pagados.
 *  - payment_orders.kind: 'service' (pedido completo) o 'addons' (solo los
 *    complementos agregados a un pedido ya pagado).
 *  - payment_order_items.addon_id / tax_rate: qué complemento cubre la línea
 *    y con qué IVA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addon_catalog_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(16)->after('price_cents');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->nullable()->after('price_cents');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->string('kind', 20)->default('service')->after('service_id')->index();
        });

        Schema::table('payment_order_items', function (Blueprint $table) {
            $table->foreignId('addon_id')->nullable()->after('payment_order_id')
                ->constrained('addons')->nullOnDelete();
            $table->decimal('tax_rate', 5, 2)->nullable()->after('amount_cents');
        });

        $this->backfillPaidAt();
    }

    /**
     * Marca como pagados los complementos existentes que ya cubrió una orden
     * pagada (generada después de agregarse el complemento). Los pedidos que
     * nunca tuvieron órdenes (datos anteriores/importados) conservan sus
     * complementos visibles para el cliente.
     */
    private function backfillPaidAt(): void
    {
        DB::table('addons')->orderBy('id')->chunkById(500, function ($addons): void {
            foreach ($addons as $addon) {
                $orders = DB::table('payment_orders')->where('service_id', $addon->service_id);

                if (! (clone $orders)->exists()) {
                    DB::table('addons')->where('id', $addon->id)->update(['paid_at' => $addon->created_at]);

                    continue;
                }

                $paidAt = (clone $orders)
                    ->where('status', 'paid')
                    ->where('created_at', '>=', $addon->created_at)
                    ->min('paid_at');

                if ($paidAt) {
                    DB::table('addons')->where('id', $addon->id)->update(['paid_at' => $paidAt]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('addon_id');
            $table->dropColumn('tax_rate');
        });

        Schema::table('payment_orders', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });

        Schema::table('addons', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'paid_at']);
        });

        Schema::table('addon_catalog_items', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};

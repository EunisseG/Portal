<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo a favor (crédito) del cliente, en centavos. Se mantiene como la suma
 * de sus movimientos de crédito; nunca es negativo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('credit_balance_cents')->default(0)->after('billing_address');
            $table->string('credit_currency', 3)->default('MXN')->after('credit_balance_cents');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['credit_balance_cents', 'credit_currency']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de dirección estructurada del cliente (estilo WHMCS). Se conserva la
 * columna legada `billing_address` (texto libre) por compatibilidad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('address_line1')->nullable()->after('billing_address');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city')->nullable()->after('address_line2');
            $table->string('state')->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('country', 2)->nullable()->after('postal_code');  // ISO-3166 alpha-2
            // Contacto que recibe la facturación: 'owner' (titular) o el id del
            // sub-usuario. Informativo por ahora.
            $table->string('billing_contact')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'address_line1', 'address_line2', 'city', 'state',
                'postal_code', 'country', 'billing_contact',
            ]);
        });
    }
};

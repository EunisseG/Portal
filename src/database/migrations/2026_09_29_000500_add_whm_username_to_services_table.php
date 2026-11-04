<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usuario (cuenta) de WHM asociado a un servicio de hosting. Es el vínculo
 * servicio ↔ cuenta creada en el WHM del servidor; se usa para suspender,
 * reactivar y consultar la cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('whm_username')->nullable()->after('server_id');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('whm_username');
        });
    }
};

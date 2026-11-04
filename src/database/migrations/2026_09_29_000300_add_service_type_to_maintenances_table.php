<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de servicio afectado por un mantenimiento (hosting, email, license,
 * other). Si es null, el mantenimiento es global. El server_id solo aplica
 * cuando service_type = 'hosting' (acota a un servidor concreto).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->string('service_type')->nullable()->after('server_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};

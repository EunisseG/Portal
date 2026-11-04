<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servidor afectado por un mantenimiento. Si es null, el mantenimiento es
 * global (aplica a todos los clientes); si apunta a un servidor, solo aplica
 * a los clientes con servicios en ese servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->foreignId('server_id')
                ->nullable()
                ->after('id')
                ->constrained('servers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('server_id');
        });
    }
};

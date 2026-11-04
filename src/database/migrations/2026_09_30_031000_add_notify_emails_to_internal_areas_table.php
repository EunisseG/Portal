<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos de notificación del área interna: a quién(es) avisar cuando un
 * empleado levanta un ticket para esa área (su agente o buzón). Pueden ser
 * varios; se guardan como una cadena separada por comas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_areas', function (Blueprint $table): void {
            $table->string('notify_emails', 1000)->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('internal_areas', function (Blueprint $table): void {
            $table->dropColumn('notify_emails');
        });
    }
};

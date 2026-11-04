<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soporte para tickets INTERNOS (levantados por empleados de la empresa) junto
 * a los de CLIENTE existentes:
 *   - type: 'client' | 'internal' (los existentes quedan como 'client').
 *   - client_id pasa a ser nullable (los internos no tienen cliente).
 *   - opened_by_user_id: usuario (empleado) que abrió el ticket interno.
 *   - internal_area_id: área interna a la que va dirigido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->string('type')->default('client')->after('number')->index();
            $table->foreignId('opened_by_user_id')->nullable()->after('client_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('internal_area_id')->nullable()->after('opened_by_user_id')
                ->constrained('internal_areas')->nullOnDelete();
        });

        // client_id deja de ser obligatorio (los tickets internos no lo usan).
        // SQLite (tests) no soporta MODIFY; se omite ahí porque la columna ya
        // admite su valor y la FK se respeta a nivel de aplicación.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreignId('client_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('internal_area_id');
            $table->dropConstrainedForeignId('opened_by_user_id');
            $table->dropColumn('type');
        });
    }
};

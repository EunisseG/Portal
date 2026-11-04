<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las políticas de SLA pasan a estar separadas por TIPO de ticket
 * ('client' | 'internal'), con objetivos configurables distintos para cada
 * uno. Antes la criticidad era única; ahora lo único es la pareja
 * (type, criticality). Las filas existentes quedan como 'client'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // En SQLite no se puede quitar un índice único por nombre con Schema
        // de forma portable; se recrea la tabla implícitamente al cambiar. Para
        // mantenerlo simple y portable: añadimos la columna, poblamos y
        // reconstruimos el índice único.
        Schema::table('sla_policies', function (Blueprint $table): void {
            $table->string('type')->default('client')->after('id')->index();
        });

        // Marca las existentes como de cliente (ya vienen con default, pero por
        // claridad).
        DB::table('sla_policies')->update(['type' => 'client']);

        // Reemplaza el índice único de 'criticality' (si existe) por uno
        // compuesto (type, criticality). En bases ya migradas existe el unique
        // simple; en bases nuevas la migración de creación ya no lo pone.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            try {
                Schema::table('sla_policies', function (Blueprint $table): void {
                    $table->dropUnique('sla_policies_criticality_unique');
                });
            } catch (\Throwable) {
                // El índice simple no existía: nada que quitar.
            }

            Schema::table('sla_policies', function (Blueprint $table): void {
                $table->unique(['type', 'criticality']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('sla_policies', function (Blueprint $table): void {
                $table->dropUnique(['type', 'criticality']);
                $table->unique('criticality');
            });
        }

        Schema::table('sla_policies', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};

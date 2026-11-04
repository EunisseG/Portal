<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El ejecutivo de ventas pasa de ligarse al CLIENTE a ligarse a cada SERVICIO
 * (producto contratado). Así un cliente puede tener dos servicios con
 * ejecutivos distintos, y queda una única fuente de verdad (el servicio).
 *
 * Para no perder las asignaciones actuales, se copia el ejecutivo del cliente
 * a cada uno de sus servicios antes de eliminar la columna del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Nueva columna en services.
        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('sales_rep_id')
                ->nullable()
                ->after('client_id')
                ->constrained('sales_reps')
                ->nullOnDelete();
        });

        // 2) Copiar el ejecutivo del cliente a todos sus servicios.
        DB::table('services')
            ->whereNull('sales_rep_id')
            ->update([
                'sales_rep_id' => DB::raw(
                    '(select sales_rep_id from clients where clients.id = services.client_id)'
                ),
            ]);

        // 3) Eliminar la columna del cliente (ya migrada a los servicios).
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_rep_id');
        });
    }

    public function down(): void
    {
        // Restaura la columna en clients.
        Schema::table('clients', function (Blueprint $table): void {
            $table->foreignId('sales_rep_id')
                ->nullable()
                ->after('user_id')
                ->constrained('sales_reps')
                ->nullOnDelete();
        });

        // Recupera un ejecutivo por cliente a partir de sus servicios (el de
        // menor id de servicio, como aproximación; es un camino de reversa).
        DB::table('clients')->update([
            'sales_rep_id' => DB::raw(
                '(select sales_rep_id from services where services.client_id = clients.id '
                .'and services.sales_rep_id is not null order by services.id limit 1)'
            ),
        ]);

        Schema::table('services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_rep_id');
        });
    }
};

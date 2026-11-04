<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de auditorías de dependencias (paquetes de Composer y npm). Cada
 * fila es una "foto" del estado de seguridad en un momento: cuántas
 * vulnerabilidades por severidad y ecosistema, y el detalle en JSON para
 * mostrarlo en el panel. Permite comparar corridas y detectar si apareció algo
 * nuevo respecto a la última revisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependency_audits', function (Blueprint $table): void {
            $table->id();

            // Resumen rápido para listados/KPIs sin tener que abrir el JSON.
            $table->unsignedInteger('composer_critical')->default(0);
            $table->unsignedInteger('composer_high')->default(0);
            $table->unsignedInteger('composer_medium')->default(0);
            $table->unsignedInteger('composer_low')->default(0);
            $table->unsignedInteger('composer_total')->default(0);

            $table->unsignedInteger('npm_critical')->default(0);
            $table->unsignedInteger('npm_high')->default(0);
            $table->unsignedInteger('npm_medium')->default(0);
            $table->unsignedInteger('npm_low')->default(0);
            $table->unsignedInteger('npm_total')->default(0);

            // Total de vulnerabilidades (suma de ambos ecosistemas).
            $table->unsignedInteger('total_vulnerabilities')->default(0);

            // true si alguna de las dos auditorías no se pudo ejecutar (sin red,
            // herramienta ausente, etc.). El detalle del error va en `detail`.
            $table->boolean('has_errors')->default(false);

            // Detalle normalizado: paquetes afectados por ecosistema, errores, etc.
            $table->json('detail')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_audits');
    }
};

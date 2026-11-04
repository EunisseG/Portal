<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Áreas internas de la empresa (RH, Sistemas, Finanzas, etc.). Se usan para
 * clasificar a los empleados y para dirigir los tickets internos al área que
 * corresponde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_areas');
    }
};

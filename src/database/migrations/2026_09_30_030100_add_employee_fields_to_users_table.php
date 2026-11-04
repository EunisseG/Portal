<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de empleado en users: número de empleado (identificador con el que
 * inicia sesión en el portal interno) y el área a la que pertenece. Solo los
 * usuarios con rol "employee" los usan; para el resto quedan en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('employee_number')->nullable()->unique()->after('parent_client_id');
            $table->foreignId('internal_area_id')->nullable()->after('employee_number')
                ->constrained('internal_areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('internal_area_id');
            $table->dropColumn('employee_number');
        });
    }
};

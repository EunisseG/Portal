<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos PIN de un solo uso para el acceso de empleados al portal interno.
 * El empleado introduce su número de empleado, se genera un PIN que se envía a
 * su correo, y con él completa el inicio de sesión. El PIN caduca y se marca
 * como usado al validarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_login_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Hash del PIN (nunca se guarda en claro).
            $table->string('code_hash');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_login_codes');
    }
};

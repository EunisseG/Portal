<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correo secundario (de contacto/recuperación). No es un correo de login; sirve
 * para recibir el enlace de restablecimiento de contraseña si el usuario pierde
 * acceso a su correo principal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('secondary_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('secondary_email');
        });
    }
};

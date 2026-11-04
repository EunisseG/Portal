<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexión WHM propia de cada servidor. Permite operar hosting (crear,
 * suspender, reactivar cuentas) contra el WHM del servidor donde vive el
 * servicio, en lugar de una única integración WHM global.
 *
 * El token se guarda cifrado (cast 'encrypted' en el modelo Server).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->boolean('whm_enabled')->default(false)->after('is_active');
            $table->string('whm_base_url')->nullable()->after('whm_enabled');
            $table->string('whm_user')->nullable()->after('whm_base_url');
            $table->text('whm_token')->nullable()->after('whm_user');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['whm_enabled', 'whm_base_url', 'whm_user', 'whm_token']);
        });
    }
};

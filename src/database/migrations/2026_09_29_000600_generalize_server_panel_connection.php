<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generaliza la conexión del servidor de "solo WHM" a un panel de control
 * configurable (WHM/cPanel o Plesk), con esquema/host/puerto separados y
 * ajustes de aprovisionamiento (verificar TLS, prefijo, longitud de contraseña).
 *
 * Copia los datos whm_* existentes al nuevo esquema panel_* (extrayendo host y
 * puerto de la antigua whm_base_url). Las columnas whm_* se conservan por
 * compatibilidad hasta confirmar la migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('panel')->nullable()->after('is_active');          // whm | plesk | null
            $table->string('panel_scheme')->default('https')->after('panel'); // https | http
            $table->string('panel_host')->nullable()->after('panel_scheme');
            $table->unsignedInteger('panel_port')->nullable()->after('panel_host');
            $table->string('panel_user')->nullable()->after('panel_port');
            $table->text('panel_token')->nullable()->after('panel_user');     // cifrado
            $table->boolean('panel_verify_tls')->default(true)->after('panel_token');
            $table->string('panel_user_prefix')->nullable()->after('panel_verify_tls');
            $table->unsignedSmallInteger('panel_password_length')->default(16)->after('panel_user_prefix');
        });

        // Migrar datos existentes de whm_* → panel_*.
        foreach (DB::table('servers')->whereNotNull('whm_base_url')->get() as $server) {
            $scheme = 'https';
            $host = $server->whm_base_url;
            $port = 2087;

            if (preg_match('#^(https?)://([^:/]+)(?::(\d+))?#i', (string) $server->whm_base_url, $m)) {
                $scheme = strtolower($m[1]);
                $host = $m[2];
                $port = isset($m[3]) ? (int) $m[3] : ($scheme === 'https' ? 2087 : 2086);
            }

            DB::table('servers')->where('id', $server->id)->update([
                'panel' => $server->whm_enabled ? 'whm' : null,
                'panel_scheme' => $scheme,
                'panel_host' => $host,
                'panel_port' => $port,
                'panel_user' => $server->whm_user,
                'panel_token' => $server->whm_token, // ya cifrado con el mismo APP_KEY
                'panel_verify_tls' => false,         // WHM solía usar withoutVerifying()
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'panel', 'panel_scheme', 'panel_host', 'panel_port',
                'panel_user', 'panel_token', 'panel_verify_tls',
                'panel_user_prefix', 'panel_password_length',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nameservers sugeridos para los dominios aprovisionados en este servidor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('ns1')->nullable()->after('panel_password_length');
            $table->string('ns2')->nullable()->after('ns1');
            $table->string('ns3')->nullable()->after('ns2');
            $table->string('ns4')->nullable()->after('ns3');
        });
    }

    public function down(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn(['ns1', 'ns2', 'ns3', 'ns4']);
        });
    }
};

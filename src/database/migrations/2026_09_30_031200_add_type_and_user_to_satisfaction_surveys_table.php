<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La encuesta de satisfacción ahora aplica también a tickets INTERNOS: se
 * añade 'type' ('client' | 'internal') y 'user_id' (el empleado que responde,
 * para internos). client_id pasa a ser nullable (los internos no tienen
 * cliente). Las encuestas existentes quedan como 'client'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('satisfaction_surveys', function (Blueprint $table): void {
            $table->string('type')->default('client')->after('id')->index();
            $table->foreignId('user_id')->nullable()->after('client_id')
                ->constrained('users')->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('satisfaction_surveys', function (Blueprint $table): void {
                $table->foreignId('client_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('satisfaction_surveys', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('type');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-usuarios de cliente: un usuario puede pertenecer a la cuenta de un
 * cliente (titular) sin ser el dueño. parent_client_id apunta al Client del
 * titular. El titular tiene su propio registro Client (users.id = clients.user_id)
 * y parent_client_id nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_client_id')
                ->nullable()
                ->after('status')
                ->constrained('clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_client_id');
        });
    }
};

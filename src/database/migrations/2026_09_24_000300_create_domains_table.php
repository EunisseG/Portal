<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            // Un dominio puede existir independiente o ligado a un servicio de hosting.
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->index();       // ejemplo.com
            $table->string('status')->index();      // DomainStatus
            $table->date('registered_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->boolean('auto_renew')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};

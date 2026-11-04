<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->index();          // ServiceType
            $table->string('name');
            $table->string('status')->index();         // ServiceStatus
            $table->string('billing_cycle')->nullable(); // BillingCycle
            // Precio informativo en la unidad menor (centavos). Moneda MXN por defecto.
            $table->unsignedBigInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

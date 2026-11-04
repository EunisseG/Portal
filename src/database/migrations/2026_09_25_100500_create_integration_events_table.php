<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('event_name')->index();       // TicketCreated, InvoiceCreated...
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['integration_id', 'event_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_events');
    }
};

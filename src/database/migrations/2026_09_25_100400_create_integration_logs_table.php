<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');                     // ticket.created, webhook.received, test_connection...
            $table->string('direction')->default('outbound'); // outbound/inbound
            $table->boolean('success')->default(true);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('message')->nullable();       // error legible, SIN secretos
            $table->string('internal_reference')->nullable(); // Ticket:1025
            $table->string('external_reference')->nullable(); // MondayItem:458721
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();          // ej. TK-2026-00001
            // Nullable: los tickets INTERNOS (empleados) no tienen cliente.
            // Ver migración 2026_09_30_030200 para los campos de ticket interno.
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->string('department');                 // TicketDepartment
            $table->string('priority')->index();          // TicketPriority
            $table->string('status')->index();            // TicketStatus
            $table->timestamp('last_reply_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

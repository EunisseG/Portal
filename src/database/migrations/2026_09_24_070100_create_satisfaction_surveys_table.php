<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained()->cascadeOnDelete();
            // Nullable: las encuestas de tickets INTERNOS no tienen cliente
            // (se ligan al empleado vía user_id). Ver migración 2026_09_30_031200.
            $table->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable(); // 1..5
            $table->text('comment')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaction_surveys');
    }
};

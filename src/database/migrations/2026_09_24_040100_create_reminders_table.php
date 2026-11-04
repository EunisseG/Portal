<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            // Entidad recordada (service, domain, addon) de forma polimórfica.
            $table->morphs('remindable');
            // Ventana del recordatorio en días (30, 15, 7, 1).
            $table->unsignedSmallInteger('days_before');
            // Fecha de vencimiento a la que corresponde este recordatorio.
            $table->date('due_date');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Evita reenviar el mismo recordatorio (misma entidad + ventana + vencimiento).
            $table->unique(['remindable_type', 'remindable_id', 'days_before', 'due_date'], 'reminders_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operaciones sobre dependencias lanzadas desde el panel: actualizaciones (con
 * su respaldo) y restauraciones (de código o de base de datos) a partir de un
 * respaldo previo. Sirve de historial y de estado en vivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dependency_updates', function (Blueprint $table) {
            $table->id();
            $table->string('type');                       // update | restore_code | restore_database
            $table->string('status')->default('queued')->index(); // queued | running | succeeded | failed
            // Para restauraciones: la actualización cuyo respaldo se usa.
            $table->foreignId('source_update_id')->nullable()->constrained('dependency_updates')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('packages')->nullable();         // paquetes que se intentó actualizar
            $table->string('backup_path')->nullable();    // carpeta del respaldo (solo updates)
            $table->unsignedBigInteger('backup_size_bytes')->nullable();
            $table->timestamp('backup_pruned_at')->nullable();
            $table->text('bypass_secret')->nullable();    // secreto del modo mantenimiento (cifrado)
            $table->boolean('maintenance_left_on')->default(false);
            $table->foreignId('audit_before_id')->nullable()->constrained('dependency_audits')->nullOnDelete();
            $table->foreignId('audit_after_id')->nullable()->constrained('dependency_audits')->nullOnDelete();
            $table->text('summary')->nullable();          // resultado legible
            $table->longText('log')->nullable();          // bitácora de pasos
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependency_updates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->morphs('referenceable');             // p. ej. Ticket #1025
            $table->string('external_id')->index();      // p. ej. Monday item 458721
            $table->string('external_url')->nullable();
            $table->string('sync_status')->default('pending'); // pending/synced/error
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['integration_id', 'referenceable_type', 'referenceable_id'],
                'integration_reference_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_references');
    }
};

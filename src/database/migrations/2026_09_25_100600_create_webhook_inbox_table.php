<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_inbox', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->index();
            $table->string('external_event_id');
            $table->boolean('signature_valid')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('payload_hash');
            $table->timestamp('created_at')->nullable();

            $table->unique(['provider', 'external_event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_inbox');
    }
};

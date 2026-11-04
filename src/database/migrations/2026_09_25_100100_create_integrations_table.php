<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();            // 'monday', 'smtp', 'custom_api'...
            $table->string('name');
            $table->string('capability')->index();      // IntegrationCapability
            $table->boolean('is_enabled')->default(false)->index();
            $table->string('status')->default('not_configured'); // connected/error/not_configured/disabled
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error')->nullable();
            $table->json('config')->nullable();          // NO secretos
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};

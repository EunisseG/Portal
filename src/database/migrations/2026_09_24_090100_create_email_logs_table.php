<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient')->index();
            $table->string('subject');
            $table->longText('body')->nullable();
            $table->json('context')->nullable();
            $table->boolean('success')->default(true);
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

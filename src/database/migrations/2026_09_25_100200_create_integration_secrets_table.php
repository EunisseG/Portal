<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_secrets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('key');                       // api_key, bearer_token, client_secret, password...
            $table->text('value');                       // cifrado at-rest
            $table->string('last4', 8)->nullable();      // para mostrar enmascarado
            $table->timestamps();

            $table->unique(['integration_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_secrets');
    }
};

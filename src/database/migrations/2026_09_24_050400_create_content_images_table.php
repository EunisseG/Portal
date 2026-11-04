<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_images', function (Blueprint $table) {
            $table->id();
            // Imagen de galería asociada a una noticia o artículo (polimórfico).
            $table->morphs('imageable');
            $table->string('path');            // disco público
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_images');
    }
};

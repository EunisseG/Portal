<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo administrable de tipos de producto (hosting, correo, licencia,
 * custom, plugin, etc.). Reemplaza al enum fijo para que el admin pueda
 * agregar sus propios tipos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_type_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();   // identificador estable (ej. 'plugin')
            $table->string('label');            // etiqueta visible (ej. 'Plugin')
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Semilla con los tipos que ya existían como enum.
        $now = now();
        DB::table('product_type_items')->insert([
            ['slug' => 'hosting', 'label' => 'Hosting', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'email', 'label' => 'Correo', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'license', 'label' => 'Licencia', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'custom', 'label' => 'Custom', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_type_items');
    }
};

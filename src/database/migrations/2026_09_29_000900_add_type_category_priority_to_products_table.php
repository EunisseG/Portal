<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade a los productos el tipo, la categoría y la prioridad (orden manual)
 * para organizarlos como en el catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
            $table->foreignId('category_id')->nullable()->after('type')
                ->constrained('product_categories')->nullOnDelete();
            $table->unsignedInteger('priority')->default(1)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['type', 'priority']);
        });
    }
};

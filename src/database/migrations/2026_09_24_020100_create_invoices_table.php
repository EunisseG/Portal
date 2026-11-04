<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('folio')->nullable()->index();  // número de factura / UUID CFDI
            $table->string('uuid')->nullable()->index();    // Folio Fiscal (CFDI) si viene del XML
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->string('currency', 3)->default('MXN');
            $table->string('status')->default('pending')->index();
            $table->date('issued_at')->nullable();
            $table->date('due_at')->nullable()->index();
            $table->string('pdf_path');                     // archivo obligatorio (storage privado)
            $table->string('xml_path')->nullable();         // XML CFDI opcional
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

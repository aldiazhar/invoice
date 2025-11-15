<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create(config('invoice.tables.invoice_items', 'invoice_items'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained(
                config('invoice.tables.invoices', 'invoices')
            )->onDelete('cascade');
            
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->string('sku')->nullable();
            $table->text('notes')->nullable();
            $table->index('invoice_id');
            $table->index('sku');
        });
    }

    public function down()
    {
        Schema::dropIfExists(config('invoice.tables.invoice_items', 'invoice_items'));
    }
};
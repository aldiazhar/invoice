<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Create invoices table
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            
            // Polymorphic relation for payer (who pays)
            $table->morphs('payer');
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            
            // Polymorphic relation for invoiceable (what is being paid)
            $table->morphs('invoiceable');
            
            $table->text('description')->nullable();
            
            // Amount breakdown
            $table->decimal('subtotal_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['payer_id', 'payer_type']);
            $table->index(['invoiceable_id', 'invoiceable_type']);
            $table->index('status');
            $table->index('due_date');
            $table->index('paid_at');
        });

        // Create invoice_items table
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            
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
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
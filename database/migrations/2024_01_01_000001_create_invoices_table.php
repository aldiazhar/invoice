<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            
            $table->morphs('payer');
            $table->string('payer_name')->nullable();
            $table->string('payer_email')->nullable();
            
            $table->morphs('invoiceable');
            
            $table->text('description')->nullable();
            
            $table->decimal('subtotal_amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            
            $table->json('metadata')->nullable();
            
            $table->boolean('is_recurring')->default(false);
            $table->string('recurring_frequency')->nullable();
            $table->integer('recurring_interval')->default(1);
            $table->timestamp('recurring_end_date')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->foreignId('parent_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['payer_id', 'payer_type']);
            $table->index(['invoiceable_id', 'invoiceable_type']);
            $table->index('status');
            $table->index('due_date');
            $table->index('paid_at');
            $table->index(['is_recurring', 'next_billing_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
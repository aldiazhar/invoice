<?php

namespace Aldiazhar\Invoice\Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function complete_invoice_flow()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $invoice = $user->invoice()
            ->pay($order)
            ->items([
                ['name' => 'Product A', 'price' => 50000, 'quantity' => 2],
                ['name' => 'Product B', 'price' => 75000, 'quantity' => 1],
            ])
            ->tax(15000)
            ->discount(10000)
            ->description('Order #123')
            ->create();

        $this->assertEquals(180000, $invoice->total_amount);
        $this->assertEquals('pending', $invoice->status);

        $invoice->addPayment(100000, 'bank_transfer');
        $this->assertEquals(80000, $invoice->getRemainingAmount());

        $invoice->addPayment(80000, 'credit_card');
        
        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertEquals(2, $invoice->payments()->count());
    }
}
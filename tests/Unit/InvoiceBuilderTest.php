<?php

namespace Aldiazhar\Invoice\Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\TopUp;
use Aldiazhar\Invoice\Facades\Invoice;
use Aldiazhar\Invoice\Exceptions\InvoiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceBuilderTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_create_simple_invoice()
    {
        $user = User::factory()->create();
        $topup = TopUp::factory()->create(['amount' => 100000]);

        $invoice = $user->invoice()
            ->pay($topup)
            ->item('Top Up', 100000)
            ->create();

        $this->assertDatabaseHas('invoices', [
            'payer_id' => $user->id,
            'invoiceable_id' => $topup->id,
            'total_amount' => 100000,
            'status' => 'pending',
        ]);

        $this->assertEquals(1, $invoice->items()->count());
    }

    /** @test */
    public function it_can_create_invoice_with_multiple_items()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create();

        $invoice = $user->invoice()
            ->pay($order)
            ->item('Product A', 50000, 2)
            ->item('Product B', 75000, 1)
            ->create();

        $this->assertEquals(175000, $invoice->total_amount);
        $this->assertEquals(2, $invoice->items()->count());
    }

    /** @test */
    public function it_can_add_tax_and_discount()
    {
        $user = User::factory()->create();
        $topup = TopUp::factory()->create(['amount' => 115000]);

        $invoice = $user->invoice()
            ->pay($topup)
            ->item('Top Up', 100000)
            ->tax(20000)
            ->discount(5000)
            ->create();

        $this->assertEquals(100000, $invoice->subtotal_amount);
        $this->assertEquals(20000, $invoice->tax_amount);
        $this->assertEquals(5000, $invoice->discount_amount);
        $this->assertEquals(115000, $invoice->total_amount);
    }

    /** @test */
    public function it_throws_exception_for_negative_price()
    {
        $this->expectException(InvoiceException::class);
        $this->expectExceptionMessage('negative price');

        $user = User::factory()->create();
        $topup = TopUp::factory()->create();

        $user->invoice()
            ->pay($topup)
            ->item('Invalid', -100)
            ->create();
    }

    /** @test */
    public function it_throws_exception_for_invalid_quantity()
    {
        $this->expectException(InvoiceException::class);

        $user = User::factory()->create();
        $topup = TopUp::factory()->create();

        $user->invoice()
            ->pay($topup)
            ->item('Invalid', 100, 0)
            ->create();
    }

    /** @test */
    public function it_throws_exception_when_amount_mismatch()
    {
        $this->expectException(InvoiceException::class);
        $this->expectExceptionMessage('mismatch');

        $user = User::factory()->create();
        $topup = TopUp::factory()->create(['amount' => 100000]);

        $user->invoice()
            ->pay($topup)
            ->item('Top Up', 50000)
            ->create();
    }

    /** @test */
    public function it_can_bypass_strict_validation()
    {
        $user = User::factory()->create();
        $topup = TopUp::factory()->create(['amount' => 100000]);

        $invoice = $user->invoice()
            ->pay($topup)
            ->item('Top Up', 50000)
            ->withoutStrictValidation()
            ->create();

        $this->assertEquals(50000, $invoice->total_amount);
    }

    /** @test */
    public function it_can_create_recurring_invoice()
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $invoice = $user->invoice()
            ->pay($subscription)
            ->item('Monthly Premium', 99000)
            ->makeRecurring('monthly', now()->addYear())
            ->create();

        $this->assertTrue($invoice->is_recurring);
        $this->assertEquals('monthly', $invoice->recurring_frequency);
        $this->assertNotNull($invoice->next_billing_date);
    }

    /** @test */
    public function it_executes_callbacks_after_create()
    {
        $user = User::factory()->create();
        $topup = TopUp::factory()->create(['amount' => 100000]);
        $callbackExecuted = false;

        $invoice = $user->invoice()
            ->pay($topup)
            ->item('Top Up', 100000)
            ->after(function($invoice) use (&$callbackExecuted) {
                $callbackExecuted = true;
            })
            ->create();

        $this->assertTrue($callbackExecuted);
    }
}
<?php

namespace Aldiazhar\Invoice\Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\TopUp;
use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Exceptions\InvoiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_mark_invoice_as_paid()
    {
        $invoice = $this->createInvoice();

        $invoice->markAsPaid();

        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    /** @test */
    public function it_throws_exception_when_marking_paid_invoice_as_paid()
    {
        $this->expectException(InvoiceException::class);

        $invoice = $this->createInvoice();
        $invoice->markAsPaid();
        $invoice->markAsPaid();
    }

    /** @test */
    public function it_can_cancel_pending_invoice()
    {
        $invoice = $this->createInvoice();

        $invoice->cancel();

        $this->assertEquals('cancelled', $invoice->status);
    }

    /** @test */
    public function it_cannot_cancel_paid_invoice()
    {
        $this->expectException(InvoiceException::class);

        $invoice = $this->createInvoice();
        $invoice->markAsPaid();
        $invoice->cancel();
    }

    /** @test */
    public function it_can_check_if_overdue()
    {
        $invoice = $this->createInvoice();
        $invoice->update(['due_date' => now()->subDay()]);

        $this->assertTrue($invoice->isOverdue());
    }

    /** @test */
    public function it_can_add_partial_payment()
    {
        $invoice = $this->createInvoice(['total_amount' => 100000]);

        $payment = $invoice->addPayment(50000, 'bank_transfer');

        $this->assertEquals(50000, $invoice->getPaidAmount());
        $this->assertEquals(50000, $invoice->getRemainingAmount());
        $this->assertFalse($invoice->isFullyPaid());
    }

    /** @test */
    public function it_marks_as_paid_when_fully_paid()
    {
        $invoice = $this->createInvoice(['total_amount' => 100000]);

        $invoice->addPayment(100000, 'bank_transfer');

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
    }

    /** @test */
    public function it_throws_exception_when_payment_exceeds_remaining()
    {
        $this->expectException(InvoiceException::class);

        $invoice = $this->createInvoice(['total_amount' => 100000]);
        $invoice->addPayment(150000, 'bank_transfer');
    }

    /** @test */
    public function it_can_generate_next_recurring_invoice()
    {
        $invoice = $this->createInvoice([
            'is_recurring' => true,
            'recurring_frequency' => 'monthly',
            'next_billing_date' => now()->addMonth(),
        ]);

        $newInvoice = $invoice->generateNextInvoice();

        $this->assertNotNull($newInvoice);
        $this->assertEquals($invoice->id, $newInvoice->parent_invoice_id);
        $this->assertEquals($invoice->items()->count(), $newInvoice->items()->count());
    }

    /** @test */
    public function it_logs_activity_when_status_changed()
    {
        $invoice = $this->createInvoice();

        $invoice->markAsPaid();

        $activity = $invoice->activities()->first();
        $this->assertNotNull($activity);
        $this->assertEquals('status_changed', $activity->action);
    }

    protected function createInvoice(array $attributes = [])
    {
        $user = User::factory()->create();
        $topup = TopUp::factory()->create(['amount' => 100000]);

        return $user->invoice()
            ->pay($topup)
            ->item('Top Up', 100000)
            ->create();
    }
}
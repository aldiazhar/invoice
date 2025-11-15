<?php

namespace Aldiazhar\Invoice\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Builders\InvoiceBuilder;

/**
 * Trait for models that can be invoiced (TopUp, Registration, Service, Order, etc.)
 * 
 * Usage:
 * class TopUp extends Model implements Invoiceable
 * {
 *     use Invoiceable;
 * }
 */
trait Invoiceable
{
    /**
     * Get all invoices for this invoiceable
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    /**
     * Start building an invoice from this invoiceable
     */
    public function bill(): InvoiceBuilder
    {
        $builder = new InvoiceBuilder();
        return $builder->pay($this);
    }

    /**
     * Alias for bill() - shorter syntax
     */
    public function inv(): InvoiceBuilder
    {
        return $this->bill();
    }

    /**
     * Quick create invoice (legacy support)
     */
    public function invoice(
        $payer,
        float $amount,
        ?string $description = null,
        array $options = []
    ): Invoice {
        $builder = (new InvoiceBuilder())
            ->from($payer)
            ->pay($this)
            ->item($description ?? $this->getInvoiceableDescription(), $amount);

        if (isset($options['tax'])) {
            $builder->tax($options['tax']);
        }

        if (isset($options['discount'])) {
            $builder->discount($options['discount']);
        }

        if (isset($options['currency'])) {
            $builder->currency($options['currency']);
        }

        if (isset($options['due_date'])) {
            $builder->due($options['due_date']);
        }

        if (isset($options['metadata'])) {
            $builder->meta($options['metadata']);
        }

        if (isset($options['callback'])) {
            $builder->after($options['callback']);
        }

        return $builder->create();
    }

    /**
     * Get paid invoices for this item
     */
    public function paidInvoices()
    {
        return $this->invoices()->paid();
    }

    /**
     * Get pending invoices for this item
     */
    public function pendingInvoices()
    {
        return $this->invoices()->pending();
    }

    /**
     * Get total revenue from this item
     */
    public function getTotalRevenue(): float
    {
        return (float) $this->invoices()->paid()->sum('total_amount');
    }

    /**
     * Get total pending amount for this item
     */
    public function getTotalPendingRevenue(): float
    {
        return (float) $this->invoices()->pending()->sum('total_amount');
    }

    /**
     * Check if this item has been paid
     */
    public function hasBeenPaid(): bool
    {
        return $this->invoices()->paid()->exists();
    }

    /**
     * Check if this item has pending invoices
     */
    public function hasPendingInvoices(): bool
    {
        return $this->invoices()->pending()->exists();
    }

    /**
     * Get invoice statistics for this item
     */
    public function getInvoiceStats(): array
    {
        return [
            'total_invoices' => $this->invoices()->count(),
            'paid_invoices' => $this->invoices()->paid()->count(),
            'pending_invoices' => $this->invoices()->pending()->count(),
            'total_revenue' => $this->getTotalRevenue(),
            'pending_revenue' => $this->getTotalPendingRevenue(),
        ];
    }

    /**
     * Default implementation for contract methods
     * Override these in your model if needed
     */
    public function getInvoiceableMetadata(): array
    {
        return [];
    }

    public function onInvoicePaid($invoice): void
    {
        // Override in your model to handle payment events
    }
}
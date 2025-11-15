<?php

namespace Aldiazhar\Invoice\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Builders\InvoiceBuilder;

/**
 * Trait for models that can pay invoices (User, Agent, Company, etc.)
 * 
 * Usage:
 * class User extends Model implements Payer
 * {
 *     use HasInvoices;
 * }
 */
trait HasInvoices
{
    /**
     * Get all invoices for this payer
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'payer');
    }

    /**
     * Start building an invoice (full method name)
     */
    public function invoice(): InvoiceBuilder
    {
        return new InvoiceBuilder($this);
    }

    /**
     * Alias for invoice() - shorter syntax
     */
    public function inv(): InvoiceBuilder
    {
        return $this->invoice();
    }

    /**
     * Quick create invoice (legacy support)
     */
    public function createInvoice(
        $invoiceable,
        float $amount,
        ?string $description = null,
        array $options = []
    ): Invoice {
        $builder = $this->invoice()
            ->pay($invoiceable)
            ->item($description ?? $invoiceable->getInvoiceableDescription(), $amount);

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
     * Get paid invoices
     */
    public function paidInvoices()
    {
        return $this->invoices()->paid();
    }

    /**
     * Get pending invoices
     */
    public function pendingInvoices()
    {
        return $this->invoices()->pending();
    }

    /**
     * Get failed invoices
     */
    public function failedInvoices()
    {
        return $this->invoices()->failed();
    }

    /**
     * Get overdue invoices
     */
    public function overdueInvoices()
    {
        return $this->invoices()->overdue();
    }

    /**
     * Get total amount paid
     */
    public function getTotalPaidAmount(): float
    {
        return (float) $this->invoices()->paid()->sum('total_amount');
    }

    /**
     * Get total pending amount
     */
    public function getTotalPendingAmount(): float
    {
        return (float) $this->invoices()->pending()->sum('total_amount');
    }

    /**
     * Get total overdue amount
     */
    public function getTotalOverdueAmount(): float
    {
        return (float) $this->invoices()->overdue()->sum('total_amount');
    }

    /**
     * Check if payer has any pending invoices
     */
    public function hasPendingInvoices(): bool
    {
        return $this->invoices()->pending()->exists();
    }

    /**
     * Check if payer has any overdue invoices
     */
    public function hasOverdueInvoices(): bool
    {
        return $this->invoices()->overdue()->exists();
    }

    /**
     * Get invoice statistics
     */
    public function getInvoiceStats(): array
    {
        return [
            'total_invoices' => $this->invoices()->count(),
            'paid_invoices' => $this->invoices()->paid()->count(),
            'pending_invoices' => $this->invoices()->pending()->count(),
            'overdue_invoices' => $this->invoices()->overdue()->count(),
            'total_paid' => $this->getTotalPaidAmount(),
            'total_pending' => $this->getTotalPendingAmount(),
            'total_overdue' => $this->getTotalOverdueAmount(),
        ];
    }
}
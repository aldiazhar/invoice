<?php

namespace Aldiazhar\Invoice\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Builders\InvoiceBuilder;

trait InvoiceableTrait
{
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'invoiceable');
    }

    public function bill(): InvoiceBuilder
    {
        $builder = new InvoiceBuilder();
        return $builder->pay($this);
    }

    public function inv(): InvoiceBuilder
    {
        return $this->bill();
    }

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

    public function paidInvoices()
    {
        return $this->invoices()->paid();
    }

    public function pendingInvoices()
    {
        return $this->invoices()->pending();
    }

    public function getTotalRevenue(): float
    {
        return (float) $this->invoices()->paid()->sum('total_amount');
    }

    public function getTotalPendingRevenue(): float
    {
        return (float) $this->invoices()->pending()->sum('total_amount');
    }

    public function hasBeenPaid(): bool
    {
        return $this->invoices()->paid()->exists();
    }

    public function hasPendingInvoices(): bool
    {
        return $this->invoices()->pending()->exists();
    }

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

    public function getInvoiceableMetadata(): array
    {
        return [];
    }

    public function onInvoicePaid($invoice): void
    {
    }
}
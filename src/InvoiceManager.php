<?php

namespace Aldiazhar\Invoice;

use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Builders\InvoiceBuilder;

class InvoiceManager
{
    /**
     * Create a new invoice builder
     */
    public function create($payer, $invoiceable): InvoiceBuilder
    {
        return (new InvoiceBuilder($payer))->pay($invoiceable);
    }

    /**
     * Find invoice by ID
     */
    public function find($id): ?Invoice
    {
        return Invoice::find($id);
    }

    /**
     * Get all pending invoices
     */
    public function pending()
    {
        return Invoice::pending()->get();
    }

    /**
     * Get all paid invoices
     */
    public function paid()
    {
        return Invoice::paid()->get();
    }

    /**
     * Get all overdue invoices
     */
    public function overdue()
    {
        return Invoice::overdue()->get();
    }

    /**
     * Get all failed invoices
     */
    public function failed()
    {
        return Invoice::failed()->get();
    }

    /**
     * Get statistics
     */
    public function stats(): array
    {
        return [
            'total' => Invoice::count(),
            'pending' => Invoice::pending()->count(),
            'paid' => Invoice::paid()->count(),
            'overdue' => Invoice::overdue()->count(),
            'failed' => Invoice::failed()->count(),
            'total_revenue' => Invoice::paid()->sum('total_amount'),
            'pending_revenue' => Invoice::pending()->sum('total_amount'),
        ];
    }
}
<?php

namespace Aldiazhar\Invoice;

use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Builders\InvoiceBuilder;

class InvoiceManager
{
    public function create($payer, $invoiceable): InvoiceBuilder
    {
        return (new InvoiceBuilder($payer))->pay($invoiceable);
    }

    public function find($id): ?Invoice
    {
        return Invoice::find($id);
    }

    public function pending()
    {
        return Invoice::pending()->get();
    }

    public function paid()
    {
        return Invoice::paid()->get();
    }

    public function overdue()
    {
        return Invoice::overdue()->get();
    }

    public function failed()
    {
        return Invoice::failed()->get();
    }

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
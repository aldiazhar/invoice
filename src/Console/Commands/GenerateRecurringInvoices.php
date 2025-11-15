<?php

namespace Aldiazhar\Invoice\Console\Commands;

use Illuminate\Console\Command;
use Aldiazhar\Invoice\Models\Invoice;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';
    
    protected $description = 'Generate recurring invoices that are due';

    public function handle()
    {
        $invoices = Invoice::where('is_recurring', true)
            ->where('next_billing_date', '<=', now())
            ->whereNull('recurring_end_date')
            ->orWhere(function ($query) {
                $query->where('is_recurring', true)
                      ->where('next_billing_date', '<=', now())
                      ->where('recurring_end_date', '>=', now());
            })
            ->get();

        $count = 0;
        
        foreach ($invoices as $invoice) {
            try {
                $newInvoice = $invoice->generateNextInvoice();
                
                if ($newInvoice) {
                    $count++;
                    $this->info("Generated invoice: {$newInvoice->invoice_number}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to generate invoice for {$invoice->invoice_number}: {$e->getMessage()}");
            }
        }

        $this->info("Total recurring invoices generated: {$count}");
        
        return Command::SUCCESS;
    }
}
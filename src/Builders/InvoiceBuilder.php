<?php

namespace Aldiazhar\Invoice\Builders;

use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Models\InvoiceItem;
use Aldiazhar\Invoice\Contracts\Payer;
use Aldiazhar\Invoice\Contracts\Invoiceable as InvoiceableContract;
use Aldiazhar\Invoice\Exceptions\InvoiceException;
use Carbon\Carbon;

class InvoiceBuilder
{
    protected $payer;
    protected $invoiceable;
    protected $items = [];
    protected $taxAmount = 0;
    protected $discountAmount = 0;
    protected $currency;
    protected $status;
    protected $dueDate;
    protected $description;
    protected $metadata = [];
    protected $afterCreateCallbacks = [];
    protected $afterPaidCallbacks = [];
    protected $strictValidation = true;
    protected $isRecurring = false;
    protected $recurringFrequency;
    protected $recurringInterval = 1;
    protected $recurringEndDate;

    public function __construct($payer = null)
    {
        $this->payer = $payer;
        $this->currency = config('invoice.currency', 'USD');
        $this->status = config('invoice.statuses.pending', 'pending');
        $this->strictValidation = config('invoice.strict_validation', true);
    }

    public function from($payer): self
    {
        if (!$payer instanceof Payer) {
            throw new InvoiceException('Payer must implement Payer interface');
        }
        
        $this->payer = $payer;
        return $this;
    }

    public function pay($invoiceable): self
    {
        if (!$invoiceable instanceof \Aldiazhar\Invoice\Contracts\Invoiceable) {
            throw new InvoiceException('Invoiceable must implement Invoiceable interface');
        }
        
        $this->invoiceable = $invoiceable;
        return $this;
    }

    public function to($invoiceable): self
    {
        return $this->pay($invoiceable);
    }

    public function by($payer): self
    {
        return $this->from($payer);
    }

    public function withInvoiceableItem(): self
    {
        if ($this->invoiceable && $this->invoiceable->getInvoiceableAmount() > 0) {
            $this->item([
                'name' => $this->invoiceable->getInvoiceableDescription(),
                'price' => $this->invoiceable->getInvoiceableAmount(),
                'quantity' => 1,
            ]);
        }
        
        return $this;
    }

    public function item($nameOrArray, float $price = null, int $quantity = 1, float $taxRate = 0): self
    {
        if (is_array($nameOrArray)) {
            $itemData = $nameOrArray;
            
            $name = $itemData['name'] ?? $itemData['description'] ?? '';
            $price = $itemData['price'] ?? 0;
            $quantity = $itemData['quantity'] ?? $itemData['qty'] ?? 1;
            $taxRate = $itemData['tax_rate'] ?? $itemData['tax'] ?? 0;
            
            $this->validateItem($name, $price, $quantity, $taxRate);
            
            $this->items[] = [
                'name' => $name,
                'description' => $itemData['description'] ?? $name,
                'price' => $price,
                'quantity' => $quantity,
                'tax_rate' => $taxRate,
                'subtotal' => $price * $quantity,
                'notes' => $itemData['notes'] ?? null,
                'sku' => $itemData['sku'] ?? null,
            ];
        } else {
            $this->validateItem($nameOrArray, $price, $quantity, $taxRate);
            
            $this->items[] = [
                'name' => $nameOrArray,
                'description' => $nameOrArray,
                'price' => $price,
                'quantity' => $quantity,
                'tax_rate' => $taxRate,
                'subtotal' => $price * $quantity,
                'notes' => null,
                'sku' => null,
            ];
        }
        
        return $this;
    }

    public function items(array $items): self
    {
        foreach ($items as $item) {
            $this->item($item);
        }
        
        return $this;
    }

    public function tax(float $amount): self
    {
        if ($amount < 0) {
            throw new InvoiceException('Tax amount cannot be negative');
        }
        
        $this->taxAmount = $amount;
        return $this;
    }

    public function discount(float $amount): self
    {
        if ($amount < 0) {
            throw new InvoiceException('Discount amount cannot be negative');
        }
        
        $this->discountAmount = $amount;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function status(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function due($date): self
    {
        if (is_string($date)) {
            $this->dueDate = Carbon::parse($date);
        } else {
            $this->dueDate = $date;
        }
        
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function meta(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function withoutStrictValidation(): self
    {
        $this->strictValidation = false;
        return $this;
    }

    public function makeRecurring(string $frequency = 'monthly', ?Carbon $endDate = null, int $interval = 1): self
    {
        $this->isRecurring = true;
        $this->recurringFrequency = $frequency;
        $this->recurringInterval = $interval;
        $this->recurringEndDate = $endDate;
        
        return $this;
    }

    public function after(callable $callback): self
    {
        $this->afterCreateCallbacks[] = $callback;
        return $this;
    }

    public function onPaid(callable $callback): self
    {
        $this->afterPaidCallbacks[] = $callback;
        return $this;
    }

    public function whenPaid(callable $callback): self
    {
        return $this->onPaid($callback);
    }

    public function create(): Invoice
    {
        $this->validate();
        
        $invoice = new Invoice();
        $invoice->invoice_number = $this->generateInvoiceNumber();
        
        $invoice->payer_type = get_class($this->payer);
        $invoice->payer_id = $this->payer->id;
        $invoice->payer_name = $this->payer->getPayerName();
        $invoice->payer_email = $this->payer->getPayerEmail();
        
        $invoice->invoiceable_type = get_class($this->invoiceable);
        $invoice->invoiceable_id = $this->invoiceable->id;
        
        $subtotal = collect($this->items)->sum('subtotal');
        $itemTaxes = collect($this->items)->sum(function ($item) {
            return $item['subtotal'] * $item['tax_rate'];
        });
        
        $invoice->subtotal_amount = $subtotal;
        $invoice->tax_amount = $this->taxAmount + $itemTaxes;
        $invoice->discount_amount = $this->discountAmount;
        $invoice->total_amount = $subtotal + $invoice->tax_amount - $invoice->discount_amount;
        
        if ($this->strictValidation && $this->invoiceable->getInvoiceableAmount() > 0) {
            $expectedAmount = $this->invoiceable->getInvoiceableAmount();
            $calculatedAmount = $invoice->total_amount;
            
            if (abs($expectedAmount - $calculatedAmount) > 0.01) {
                throw InvoiceException::amountMismatch($expectedAmount, $calculatedAmount);
            }
        }
        
        $invoice->currency = $this->currency;
        $invoice->status = $this->status;
        $invoice->description = $this->description;
        $invoice->due_date = $this->dueDate ?? now()->addDays(config('invoice.due_date_days', 30));
        
        if ($this->isRecurring) {
            $invoice->is_recurring = true;
            $invoice->recurring_frequency = $this->recurringFrequency;
            $invoice->recurring_interval = $this->recurringInterval;
            $invoice->recurring_end_date = $this->recurringEndDate;
            $invoice->next_billing_date = $this->calculateNextBillingDate();
        }
        
        $invoice->metadata = array_merge(
            $this->metadata,
            $this->payer->getPayerMetadata(),
            $this->invoiceable->getInvoiceableMetadata()
        );
        
        $invoice->after_paid_callbacks = $this->afterPaidCallbacks;
        
        $invoice->save();
        
        foreach ($this->items as $item) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'name' => $item['name'],
                'description' => $item['description'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'tax_rate' => $item['tax_rate'],
                'subtotal' => $item['subtotal'],
                'notes' => $item['notes'],
                'sku' => $item['sku'],
            ]);
        }
        
        foreach ($this->afterCreateCallbacks as $callback) {
            try {
                $callback($invoice);
            } catch (\Exception $e) {
                logger()->error('Invoice after-create callback error: ' . $e->getMessage());
            }
        }
        
        return $invoice->fresh(['items']);
    }

    protected function validateItem(string $name, float $price, int $quantity, float $taxRate): void
    {
        if (empty($name)) {
            throw new InvoiceException('Item name is required');
        }
        
        if ($price < 0) {
            throw InvoiceException::negativePrice($name, $price);
        }
        
        if ($quantity < 1) {
            throw InvoiceException::invalidQuantity($name);
        }
        
        if ($taxRate < 0 || $taxRate > 1) {
            throw InvoiceException::invalidTaxRate($name, $taxRate);
        }
    }

    protected function validate(): void
    {
        if (!$this->payer) {
            throw InvoiceException::payerRequired();
        }
        
        if (!$this->invoiceable) {
            throw InvoiceException::invoiceableRequired();
        }
        
        if (empty($this->items)) {
            throw InvoiceException::itemsRequired();
        }
        
        $subtotal = collect($this->items)->sum('subtotal');
        if ($this->discountAmount > $subtotal) {
            throw InvoiceException::discountExceedsSubtotal($this->discountAmount, $subtotal);
        }
        
        $itemTaxes = collect($this->items)->sum(function ($item) {
            return $item['subtotal'] * $item['tax_rate'];
        });
        
        $totalAmount = $subtotal + $this->taxAmount + $itemTaxes - $this->discountAmount;
        
        if ($totalAmount < 0) {
            throw InvoiceException::negativeTotalAmount(
                $subtotal, 
                $this->taxAmount + $itemTaxes, 
                $this->discountAmount
            );
        }
    }

    protected function calculateNextBillingDate(): Carbon
    {
        return match($this->recurringFrequency) {
            'daily' => now()->addDays($this->recurringInterval),
            'weekly' => now()->addWeeks($this->recurringInterval),
            'monthly' => now()->addMonths($this->recurringInterval),
            'yearly' => now()->addYears($this->recurringInterval),
            default => now()->addMonth(),
        };
    }

    protected function generateInvoiceNumber(): string
    {
        $prefix = config('invoice.invoice_number.prefix', 'INV-');
        $format = config('invoice.invoice_number.format', 'Ymd');
        $padding = config('invoice.invoice_number.padding', 4);
        
        $date = now()->format($format);
        
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . $date . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -$padding);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . $date . '-' . str_pad($newNumber, $padding, '0', STR_PAD_LEFT);
    }
}
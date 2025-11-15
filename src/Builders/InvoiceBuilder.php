<?php

namespace Aldiazhar\Invoice\Builders;

use Aldiazhar\Invoice\Models\Invoice;
use Aldiazhar\Invoice\Models\InvoiceItem;
use Aldiazhar\Invoice\Contracts\Payer;
use Aldiazhar\Invoice\Contracts\Invoiceable as InvoiceableContract;
use Aldiazhar\Invoice\Exceptions\InvoiceException;
use Illuminate\Support\Str;
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
    protected $callbacks = [];

    public function __construct($payer = null)
    {
        $this->payer = $payer;
        $this->currency = config('invoice.currency', 'USD');
        $this->status = config('invoice.statuses.pending', 'pending');
    }

    /**
     * Set the payer (who pays)
     */
    public function from($payer): self
    {
        if (!$payer instanceof Payer) {
            throw new InvoiceException('Payer must implement Payer interface');
        }
        
        $this->payer = $payer;
        return $this;
    }

    /**
     * Set the invoiceable (what is being paid)
     */
    public function pay($invoiceable): self
    {
        if (!$invoiceable instanceof InvoiceableContract) {
            throw new InvoiceException('Invoiceable must implement Invoiceable interface');
        }
        
        $this->invoiceable = $invoiceable;
        
        // Auto-add as first item if amount exists
        if ($invoiceable->getInvoiceableAmount() > 0) {
            $this->item(
                $invoiceable->getInvoiceableDescription(),
                $invoiceable->getInvoiceableAmount()
            );
        }
        
        return $this;
    }

    /**
     * Alias for pay()
     */
    public function to($invoiceable): self
    {
        return $this->pay($invoiceable);
    }

    /**
     * For invoiceable models - set who pays
     */
    public function by($payer): self
    {
        return $this->from($payer);
    }

    /**
     * Add an item to the invoice
     * 
     * Supports multiple formats:
     * 1. Array format: ->item(['name' => 'Product', 'price' => 100, ...])
     * 2. String format: ->item('Product', 100, 2, 0.1)
     */
    public function item($nameOrArray, float $price = null, int $quantity = 1, float $taxRate = 0): self
    {
        // If first parameter is array, use array format
        if (is_array($nameOrArray)) {
            $itemData = $nameOrArray;
            
            $name = $itemData['name'] ?? $itemData['description'] ?? '';
            $price = $itemData['price'] ?? 0;
            $quantity = $itemData['quantity'] ?? $itemData['qty'] ?? 1;
            $taxRate = $itemData['tax_rate'] ?? $itemData['tax'] ?? 0;
            
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
            // Traditional string format
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

    /**
     * Add multiple items at once
     */
    public function items(array $items): self
    {
        foreach ($items as $item) {
            // Each item is already an array
            $this->item($item);
        }
        
        return $this;
    }

    /**
     * Set tax amount
     */
    public function tax(float $amount): self
    {
        $this->taxAmount = $amount;
        return $this;
    }

    /**
     * Set discount amount
     */
    public function discount(float $amount): self
    {
        $this->discountAmount = $amount;
        return $this;
    }

    /**
     * Set currency
     */
    public function currency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Set status
     */
    public function status(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Set due date (accepts string or Carbon)
     */
    public function due($date): self
    {
        if (is_string($date)) {
            $this->dueDate = Carbon::parse($date);
        } else {
            $this->dueDate = $date;
        }
        
        return $this;
    }

    /**
     * Set description
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set metadata
     */
    public function meta(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    /**
     * Add callback to execute after payment
     */
    public function after(callable $callback): self
    {
        $this->callbacks[] = $callback;
        return $this;
    }

    /**
     * Create the invoice
     */
    public function create(): Invoice
    {
        $this->validate();
        
        $invoice = new Invoice();
        $invoice->invoice_number = $this->generateInvoiceNumber();
        
        // Set payer
        $invoice->payer_type = get_class($this->payer);
        $invoice->payer_id = $this->payer->id;
        $invoice->payer_name = $this->payer->getPayerName();
        $invoice->payer_email = $this->payer->getPayerEmail();
        
        // Set invoiceable
        $invoice->invoiceable_type = get_class($this->invoiceable);
        $invoice->invoiceable_id = $this->invoiceable->id;
        
        // Calculate amounts
        $subtotal = collect($this->items)->sum('subtotal');
        $itemTaxes = collect($this->items)->sum(function ($item) {
            return $item['subtotal'] * $item['tax_rate'];
        });
        
        $invoice->subtotal_amount = $subtotal;
        $invoice->tax_amount = $this->taxAmount + $itemTaxes;
        $invoice->discount_amount = $this->discountAmount;
        $invoice->total_amount = $subtotal + $invoice->tax_amount - $invoice->discount_amount;
        
        // Set other attributes
        $invoice->currency = $this->currency;
        $invoice->status = $this->status;
        $invoice->description = $this->description;
        $invoice->due_date = $this->dueDate ?? now()->addDays(config('invoice.due_date_days', 30));
        
        // Merge metadata
        $invoice->metadata = array_merge(
            $this->metadata,
            $this->payer->getPayerMetadata(),
            $this->invoiceable->getInvoiceableMetadata()
        );
        
        // Store callbacks - we'll execute them later, not serialize
        // Callbacks are stored in memory only, not in database
        $invoice->pending_callbacks = $this->callbacks;
        
        $invoice->save();
        
        // Create invoice items
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
        
        return $invoice->fresh(['items']);
    }

    /**
     * Validate builder data
     */
    protected function validate(): void
    {
        if (!$this->payer) {
            throw new InvoiceException('Payer is required');
        }
        
        if (!$this->invoiceable) {
            throw new InvoiceException('Invoiceable is required');
        }
        
        if (empty($this->items)) {
            throw new InvoiceException('At least one item is required');
        }
    }

    /**
     * Generate unique invoice number
     */
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
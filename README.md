# Laravel Invoice Package

A flexible and powerful invoice management package for Laravel applications.

## Requirements

- PHP 8.1, 8.2, or 8.3
- Laravel 10.x, 11.x, or 12.x

## Installation

```bash
composer require aldiazhar/laravel-invoice
```

### Publish Assets

```bash
php artisan vendor:publish --tag=invoice-config
php artisan vendor:publish --tag=invoice-migrations
php artisan migrate
```

## Quick Start Guide

### Two Ways to Create Invoice

**Method 1: From Payer (User) Perspective**
```php
$invoice = $user->invoice()
    ->pay($payment)     // What to pay
    ->create();
```

**Method 2: From Invoiceable (Payment) Perspective**
```php
$invoice = $payment->bill()
    ->to($user)         // Bill to whom
    ->create();
```

Both methods create the same invoice!

### 1. Setup Models

**User Model (Payer):**

```php
use Aldiazhar\Invoice\Contracts\Payer;
use Aldiazhar\Invoice\Traits\HasInvoices;

class User extends Model implements Payer
{
    use HasInvoices;

    public function getPayerName(): string
    {
        return $this->name;
    }

    public function getPayerEmail(): ?string
    {
        return $this->email;
    }

    public function getPayerAddress(): ?string
    {
        return $this->address;
    }

    public function getPayerMetadata(): array
    {
        return ['phone' => $this->phone];
    }
}
```

**Payment Model (Invoiceable):**

```php
use Aldiazhar\Invoice\Contracts\Invoiceable;
use Aldiazhar\Invoice\Traits\Invoiceable as InvoiceableTrait;

class Payment extends Model implements Invoiceable
{
    use InvoiceableTrait;

    public function getInvoiceableDescription(): string
    {
        return "Payment #{$this->id}";
    }

    public function getInvoiceableAmount(): float
    {
        return $this->amount;
    }

    public function getInvoiceableMetadata(): array
    {
        return [
            'payment_method' => $this->payment_method,
            'reference' => $this->reference_number,
        ];
    }

    public function onInvoicePaid($invoice): void
    {
        $this->update(['status' => 'completed']);
    }
}
```

### 2. Create Invoice

**Auto Item (Recommended - No items needed!):**

```php
// From Payer perspective
$invoice = $user->invoice()
    ->pay($payment)
    ->create();

// From Invoiceable perspective  
$invoice = $payment->bill()
    ->to($user)
    ->create();
```

Item otomatis dibuat dari `getInvoiceableDescription()` dan `getInvoiceableAmount()`

**Manual Single Item:**

```php
$invoice = $user->invoice()
    ->pay($payment)
    ->item('Payment Processing', 100000)
    ->create();
```

**Multiple Items:**

```php
$invoice = $user->invoice()
    ->pay($order)
    ->item('Product A', 50000, 2)
    ->item('Product B', 75000, 1)
    ->tax(10000)
    ->discount(5000)
    ->create();
```

**Using Array:**

```php
$invoice = $user->invoice()
    ->pay($order)
    ->items([
        ['name' => 'Product A', 'price' => 50000, 'quantity' => 2, 'tax_rate' => 0.11],
        ['name' => 'Product B', 'price' => 75000, 'quantity' => 1],
    ])
    ->create();
```

**Without Auto Item (Manual Control):**

```php
$invoice = $user->invoice()
    ->pay($order)
    ->withoutAutoItem()
    ->item('Custom Item', 100000)
    ->create();
```

**Advanced:**

```php
$invoice = $user->invoice()
    ->pay($order)
    ->item('Premium Package', 500000)
    ->tax(20000)
    ->discount(50000)
    ->currency('USD')
    ->due('2024-12-31')
    ->description('Monthly subscription')
    ->meta(['campaign_id' => 123])
    ->after(function($invoice) {
        // Custom logic after create
    })
    ->onPaid(function($invoice) {
        // Custom logic when paid
    })
    ->create();
```

### 3. Invoice Management

```php
$invoice->markAsPaid();
$invoice->cancel();
$invoice->markAsFailed();
$invoice->refund();

if ($invoice->isPaid()) {
    //
}

if ($invoice->isOverdue()) {
    //
}
```

### 4. Partial Payments

```php
$invoice->addPayment(50000, 'bank_transfer', [
    'reference' => 'TRX-123',
    'notes' => 'First installment',
]);

$paidAmount = $invoice->getPaidAmount();
$remaining = $invoice->getRemainingAmount();
$isFullyPaid = $invoice->isFullyPaid();
```

### 5. Recurring Invoices

```php
$invoice = $user->invoice()
    ->pay($subscription)
    ->item('Monthly Premium', 99000)
    ->makeRecurring('monthly', now()->addYear())
    ->create();

$schedule->command('invoices:generate-recurring')->daily();
```

### 6. Query & Statistics

```php
use Aldiazhar\Invoice\Facades\Invoice;

$pending = Invoice::pending();
$paid = Invoice::paid();
$overdue = Invoice::overdue();

$stats = Invoice::stats();

$userStats = $user->getInvoiceStats();
```

### 7. Activity Log

```php
$activities = $invoice->activities;

foreach ($activities as $activity) {
    echo $activity->action;
    echo $activity->description;
    echo $activity->causer->name;
}
```

## Auto Item Feature

**Default Behavior (Auto Item Enabled):**

```php
$payment = Payment::create(['amount' => 100000]);

$invoice = $user->invoice()
    ->pay($payment)
    ->create();
```

Secara otomatis membuat item dengan:
- Name: dari `getInvoiceableDescription()` 
- Price: dari `getInvoiceableAmount()`
- Quantity: 1

**Disable Auto Item:**

```php
$invoice = $user->invoice()
    ->pay($payment)
    ->withoutAutoItem()
    ->item('Custom Item', 50000)
    ->item('Another Item', 50000)
    ->create();
```

**Force Add Invoiceable Item:**

```php
$invoice = $user->invoice()
    ->pay($payment)
    ->item('Extra Service', 20000)
    ->withInvoiceableItem()
    ->create();
```

Total: 120000 (100000 from payment + 20000 from extra)

## Configuration

Edit `config/invoice.php`:

```php
return [
    'currency' => 'USD',
    'due_date_days' => 30,
    'strict_validation' => true,
    
    'invoice_number' => [
        'prefix' => 'INV-',
        'format' => 'Ymd',
        'padding' => 4,
    ],
];
```

## Validation

### Strict Mode (Default)

Invoice total must match invoiceable amount:

```php
$payment = Payment::create(['amount' => 100000]);

$invoice = $user->invoice()
    ->pay($payment)
    ->create();
```

Total: 100000 ✅

```php
$invoice = $user->invoice()
    ->pay($payment)
    ->item('Custom', 50000)
    ->create();
```

Error: Mismatch! Expected 100000, got 50000 ❌

### Disable Strict Mode

```php
$invoice = $user->invoice()
    ->pay($payment)
    ->item('Custom', 50000)
    ->withoutStrictValidation()
    ->create();
```

Total: 50000 ✅

## API Reference

### InvoiceBuilder Methods

```php
// Set who pays
->to($payer)         // Set payer (who will pay)

// Set what to pay
->pay($invoiceable)  // Set invoiceable (what to pay)

// Items
->item($name, $price, $quantity = 1, $taxRate = 0)
->items(array $items)
->withInvoiceableItem()
->withoutAutoItem()

// Amounts
->tax(float $amount)
->discount(float $amount)

// Details
->currency(string $currency)
->status(string $status)
->due($date)
->description(string $description)
->meta(array $metadata)

// Options
->withoutStrictValidation()
->makeRecurring($frequency, $endDate, $interval)

// Callbacks
->after(callable $callback)
->onPaid(callable $callback)

// Execute
->create()
```

### Invoice Methods

```php
$invoice->markAsPaid()
$invoice->cancel()
$invoice->markAsFailed()
$invoice->refund()
$invoice->addPayment($amount, $method, $data)
$invoice->generateNextInvoice()
$invoice->isPaid()
$invoice->isPending()
$invoice->isOverdue()
$invoice->getPaidAmount()
$invoice->getRemainingAmount()
$invoice->isFullyPaid()
```

### Query Scopes

```php
Invoice::pending()
Invoice::paid()
Invoice::failed()
Invoice::overdue()
Invoice::forPayer($payer)
Invoice::forInvoiceable($invoiceable)
```

## Examples

### Simple Payment Invoice (Auto Item)

```php
$payment = Payment::create([
    'user_id' => $user->id,
    'amount' => 150000,
    'payment_method' => 'bank_transfer',
]);

$invoice = $user->invoice()
    ->pay($payment)
    ->create();
```

Item dibuat otomatis dari payment!

### E-Commerce Order (Manual Items)

```php
$invoice = $user->invoice()
    ->pay($order)
    ->withoutAutoItem()
    ->items($order->items->map(fn($item) => [
        'name' => $item->product->name,
        'price' => $item->price,
        'quantity' => $item->quantity,
        'sku' => $item->product->sku,
    ])->toArray())
    ->tax($order->tax_amount)
    ->discount($order->discount_amount)
    ->create();
```

### Subscription (Recurring + Auto Item)

```php
$subscription = Subscription::create([
    'plan' => 'premium',
    'price' => 99000,
]);

$invoice = $user->invoice()
    ->pay($subscription)
    ->makeRecurring('monthly')
    ->onPaid(function($invoice) {
        $invoice->invoiceable->renew();
    })
    ->create();
```

### Service Payment (Mixed Items)

```php
$service = Service::create([
    'name' => 'Consulting',
    'base_amount' => 1200,
]);

$invoice = $user->invoice()
    ->pay($service)
    ->withInvoiceableItem()
    ->item('Travel Cost', 200)
    ->item('Materials', 100)
    ->tax(0.11)
    ->create();
```

Total: 1500 + 11% tax

## Troubleshooting

### Interface Implementation Issue

Jika muncul error "must implement Invoiceable interface", pastikan:

```php
use Aldiazhar\Invoice\Contracts\Invoiceable;
use Aldiazhar\Invoice\Traits\InvoiceableTrait;

class Payment extends Model implements Invoiceable
{
    use InvoiceableTrait;
    
    public function getInvoiceableDescription(): string
    {
        return "Payment #{$this->id}";
    }

    public function getInvoiceableAmount(): float
    {
        return $this->amount;
    }
}
```

Jangan lupa implement semua method yang required!

### Using bill()->to() vs invoice()->pay()

```php
// These are EQUIVALENT:

// Option 1: From Payer
$user->invoice()->pay($payment)->create();

// Option 2: From Invoiceable  
$payment->bill()->to($user)->create();

// Both create the same invoice!
```

### Amount Mismatch Error

```php
$payment = Payment::create(['amount' => 100000]);

$invoice = $user->invoice()
    ->pay($payment)
    ->item('Custom', 50000)
    ->withoutStrictValidation()
    ->create();
```

Atau pastikan total items = invoiceable amount

## License

MIT License

## Support

- GitHub: [aldiazhar/laravel-invoice](https://github.com/aldiazhar/laravel-invoice)
- Issues: [GitHub Issues](https://github.com/aldiazhar/laravel-invoice/issues)
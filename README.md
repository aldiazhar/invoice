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

## Quick Start

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

**TopUp Model (Invoiceable):**

```php
use Aldiazhar\Invoice\Contracts\Invoiceable as InvoiceableContract;
use Aldiazhar\Invoice\Traits\Invoiceable;

class TopUp extends Model implements InvoiceableContract
{
    use Invoiceable;

    public function getInvoiceableDescription(): string
    {
        return "Top Up - {$this->package_name}";
    }

    public function getInvoiceableAmount(): float
    {
        return $this->amount;
    }

    public function getInvoiceableMetadata(): array
    {
        return ['package_id' => $this->package_id];
    }

    public function onInvoicePaid($invoice): void
    {
        $this->update(['status' => 'completed']);
    }
}
```

### 2. Create Invoice

**Simple Invoice:**

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item('Top Up Package', 100000)
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
$invoice = $user->invoice()
    ->pay($topup)  // Amount: 100000
    ->item('Top Up', 100000)  // Must match
    ->create();
```

### Disable Strict Mode

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item('Top Up', 50000)
    ->withoutStrictValidation()
    ->create();
```

## API Reference

### InvoiceBuilder Methods

```php
->from($payer)
->pay($invoiceable)
->by($payer)
->to($invoiceable)
->item($name, $price, $quantity = 1, $taxRate = 0)
->items(array $items)
->tax(float $amount)
->discount(float $amount)
->currency(string $currency)
->status(string $status)
->due($date)
->description(string $description)
->meta(array $metadata)
->withoutStrictValidation()
->makeRecurring($frequency, $endDate, $interval)
->after(callable $callback)
->onPaid(callable $callback)
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

### E-Commerce Order

```php
$invoice = $user->invoice()
    ->pay($order)
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

### Subscription

```php
$invoice = $user->invoice()
    ->pay($subscription)
    ->item('Premium Membership', 99.99)
    ->makeRecurring('monthly')
    ->onPaid(function($invoice) {
        $invoice->invoiceable->renew();
    })
    ->create();
```

### Service Payment

```php
$invoice = $user->invoice()
    ->pay($service)
    ->item('Consulting Service', 150, 8)
    ->tax(0.11)
    ->due(now()->addDays(15))
    ->description('Project: Website Development')
    ->create();
```

## License

MIT License

## Support

- GitHub: [aldiazhar/laravel-invoice](https://github.com/aldiazhar/laravel-invoice)
- Issues: [GitHub Issues](https://github.com/aldiazhar/laravel-invoice/issues)
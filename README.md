# Aldiazhar Invoice Package

> Revolutionary Laravel invoice package with dual morph relationships and fluent builder pattern

## 🎯 Vision & Purpose

Aldiazhar Invoice Package is a Laravel package designed to simplify invoice management with a flexible morph relationship system. This package enables developers to implement complex payment systems with elegant and intuitive syntax in minutes.

## 🚀 Key Features

### 1. **Dual Morph Relationship System**
Two powerful polymorphic relationships:
- **Payer Morph**: User, Agent, Company (who pays)
- **Invoiceable Morph**: TopUp, Registration, Service (what gets paid)

### 2. **Fluent Builder Pattern**
Natural language-like syntax with flexible item format:

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item([
        'name' => 'Premium Service',
        'description' => 'Monthly premium subscription',
        'price' => 25.00,
        'quantity' => 1,
        'tax_rate' => 0.1,
        'sku' => 'PREM-001'
    ])
    ->discount(5.00)
    ->due('+7 days')
    ->after(fn($inv) => $topup->activate())
    ->create();
```

### 3. **Multi-Directional Invoice Creation**
Create invoices from anyone and for anything:

```php
// From payer side
$user->invoice()->pay($topup)

// From invoiceable side  
$topup->bill()->to($user)

// Via Facade directly
Invoice::create($user, $topup)
```

### 4. **Smart Callback System**
Automated actions after payment:

```php
->after(function($paidInvoice) {
    $user->notify(new PaymentSuccess);
    $service->activate();
    $commission->distribute();
})
```

## 📦 Installation

```bash
composer require aldiazhar/laravel-invoice
```

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=invoice-config
php artisan vendor:publish --tag=invoice-migrations
```

Run migrations:

```bash
php artisan migrate
```

## 🛠 Quick Start

### Step 1: Implement Contracts & Traits

**For Payers (User, Agent, Company)**:

```php
use Aldiazhar\Invoice\Traits\HasInvoices;
use Aldiazhar\Invoice\Contracts\Payer;

class User extends Authenticatable implements Payer
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
        return ['user_id' => $this->id];
    }
}
```

**For Invoiceables (TopUp, Registration, Service)**:

```php
use Aldiazhar\Invoice\Traits\Invoiceable as InvoiceableTrait;
use Aldiazhar\Invoice\Contracts\Invoiceable;

class TopUp extends Model implements Invoiceable
{
    use InvoiceableTrait;

    public function getInvoiceableDescription(): string
    {
        return "Top Up - {$this->type}";
    }

    public function getInvoiceableAmount(): float
    {
        return $this->amount;
    }

    public function getInvoiceableMetadata(): array
    {
        return ['topup_id' => $this->id];
    }

    public function onInvoicePaid($invoice): void
    {
        // Auto-execute when invoice is paid
        $this->update(['status' => 'active']);
    }
}
```

### Step 2: Create Invoices

```php
// User pays for TopUp with detailed items
$user = User::find(1);
$topup = TopUp::create(['amount' => 100]);

$invoice = $user->invoice()
    ->pay($topup)
    ->item([
        'name' => 'Top Up Credit',
        'description' => 'Premium credit package',
        'price' => 100.00,
        'quantity' => 1,
        'tax_rate' => 0.1, // 10% tax
        'sku' => 'TOPUP-PREM'
    ])
    ->discount(5.00)
    ->due('+7 days')
    ->after(fn($inv) => $user->notify(new PaymentSuccess($inv)))
    ->create();

// Multiple items at once
$invoice = $user->invoice()
    ->pay($order)
    ->items([
        [
            'name' => 'Product A',
            'price' => 50.00,
            'quantity' => 2,
            'tax_rate' => 0.1
        ],
        [
            'name' => 'Shipping',
            'price' => 10.00,
            'quantity' => 1
        ]
    ])
    ->create();

// Traditional format (still supported)
$invoice = $user->invoice()
    ->pay($topup)
    ->item('Service', 100.00, 1, 0.1)
    ->create();
```

### Step 3: Process Payment

```php
// Mark as paid (triggers callbacks)
$invoice->markAsPaid();

// Check status
if ($invoice->isPaid()) {
    echo "Payment successful!";
}
```

## 💡 Real-World Use Cases

### E-Commerce Platform

```php
$invoice = $customer->invoice()
    ->pay($order)
    ->items([
        [
            'name' => 'Product XYZ',
            'description' => 'Premium quality product',
            'price' => 99.99,
            'quantity' => 2,
            'tax_rate' => 0.1,
            'sku' => 'PRD-XYZ-001'
        ],
        [
            'name' => 'Shipping',
            'price' => 15.00,
            'quantity' => 1
        ]
    ])
    ->after(fn($inv) => $order->markAsPaid())
    ->create();
```

### SaaS Subscription

```php
$invoice = $company->invoice()
    ->pay($subscription)
    ->item([
        'name' => 'Pro Plan',
        'description' => 'Pro Plan - March 2024',
        'price' => 49.99,
        'sku' => 'PLAN-PRO-MONTHLY'
    ])
    ->due('+30 days')
    ->after(fn($inv) => $subscription->renew())
    ->create();
```

### Service Marketplace

```php
$invoice = $agent->invoice()
    ->pay($commissionFee)
    ->item([
        'name' => 'Platform Commission',
        'price' => 25.00
    ])
    ->discount(5.00)
    ->create();
```

## 🎨 Developer Experience

### Short Syntax Options

```php
// Full method names
$user->invoice()->pay($topup)->create();

// Short aliases
$user->inv()->pay($topup)->create();

// From invoiceable side
$topup->bill()->to($user)->create();
$topup->inv()->by($user)->create();
```

### Rich Query Methods

```php
// From payer
$user->paidInvoices()->get();
$user->pendingInvoices()->get();
$user->overdueInvoices()->get();
$user->getTotalPaidAmount();
$user->getInvoiceStats();

// From invoiceable
$topup->invoices;
$topup->getTotalRevenue();
$topup->hasBeenPaid();

// Global queries
Invoice::pending();
Invoice::paid();
Invoice::overdue();
Invoice::stats();
```

## 🔧 Advanced Features

### Multiple Items with Tax Rates

```php
$invoice = $user->invoice()
    ->pay($order)
    ->items([
        [
            'name' => 'Product A',
            'price' => 50.00,
            'quantity' => 2,
            'tax_rate' => 0.1,  // 10% tax
            'sku' => 'PRD-A'
        ],
        [
            'name' => 'Product B',
            'price' => 30.00,
            'quantity' => 1,
            'tax_rate' => 0.05, // 5% tax
            'sku' => 'PRD-B'
        ],
        [
            'name' => 'Shipping',
            'price' => 10.00,
            'quantity' => 1,
            'tax_rate' => 0     // No tax
        ]
    ])
    ->create();

// Or use traditional format
$invoice = $user->invoice()
    ->pay($order)
    ->item('Product A', 50.00, 2, 0.1)
    ->item('Product B', 30.00, 1, 0.05)
    ->item('Shipping', 10.00, 1, 0)
    ->create();
```

### Multiple Callbacks

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->after(fn($inv) => Mail::to($inv->payer_email)->send(new InvoicePaid($inv)))
    ->after(fn($inv) => Cache::forget('user_stats_' . $inv->payer_id))
    ->after(fn($inv) => Http::post('https://api.example.com/webhook', $inv->toArray()))
    ->create();
```

### Custom Metadata

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->meta([
        'payment_gateway' => 'stripe',
        'internal_reference' => 'ORD-123',
        'custom_field' => 'value',
    ])
    ->create();
```

## 🗄 Database Schema

### Invoices Table
- `invoice_number` - Unique, auto-generated
- `payer_id`, `payer_type` - Polymorphic payer
- `invoiceable_id`, `invoiceable_type` - Polymorphic invoiceable
- `subtotal_amount`, `tax_amount`, `discount_amount`, `total_amount`
- `status` - pending, paid, failed, cancelled, refunded, overdue
- `due_date`, `paid_at`
- `metadata` - JSON for additional data
- `callbacks` - Serialized callbacks

### Invoice Items Table
- `invoice_id` - Foreign key
- `name` - Product/service name
- `description` - Detailed description
- `price`, `quantity`, `tax_rate`, `subtotal`
- `sku` - Product SKU/code
- `notes` - Additional notes

## ⚡ Performance

Built-in optimizations:
- Database indexing on polymorphic columns
- Eager loading ready
- Efficient query scopes
- No N+1 queries

```php
// Prevent N+1 queries
$invoices = Invoice::with(['payer', 'invoiceable', 'items'])->get();
```

## 🎯 Use Case: User vs Agent

```php
// Users pay for TopUp AND RegistrationFee
$user = User::find(1);
$topupInvoice = $user->invoice()->pay($topup)->create();
$regFeeInvoice = $user->invoice()->pay($registrationFee)->create();

// Agents only pay for TopUp
$agent = Agent::find(1);
$topupInvoice = $agent->invoice()->pay($topup)->create();
// No registration fee for agents!
```

## 📊 Using the Facade

```php
use Aldiazhar\Invoice\Facades\Invoice;

// Get statistics
$stats = Invoice::stats();

// Find invoice
$invoice = Invoice::find(123);

// Get collections
$pending = Invoice::pending();
$paid = Invoice::paid();
$overdue = Invoice::overdue();
```

## ⚙️ Configuration

Edit `config/invoice.php`:

```php
return [
    'currency' => 'USD',
    'invoice_number' => [
        'prefix' => 'INV-',
        'format' => 'Ymd',
        'padding' => 4,
    ],
    'due_date_days' => 30,
    'routes' => [
        'enabled' => true,
        'prefix' => 'invoices',
        'middleware' => ['web', 'auth'],
    ],
    // ... more options
];
```

## 🔐 Security

- Built-in authorization ready
- Data validation
- Soft deletes for audit trail
- Secure callback execution

## 🛣 Roadmap

- [ ] Payment Gateway Integration (Stripe, PayPal, Midtrans)
- [ ] Recurring Invoices
- [ ] Multi-currency Support
- [ ] Advanced Reporting
- [ ] Webhook System
- [ ] PDF Generation
- [ ] Email Templates

## 🎉 Why Aldiazhar Invoice?

✅ **90% faster development** - Implement invoicing in minutes, not days  
✅ **Dual morph flexibility** - Pay anyone, for anything  
✅ **Natural syntax** - Code that reads like English  
✅ **Production ready** - Tested, secure, performant  
✅ **Complete solution** - From creation to payment  
✅ **Extensive documentation** - Examples for every scenario  

## 📝 License

MIT License

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 💬 Support

For issues and questions:
- GitHub Issues: [https://github.com/aldiazhar/laravel-invoice/issues](https://github.com/aldiazhar/laravel-invoice/issues)
- Email: permana.azhar.aldi@gmail.com

## 🌟 Show Your Support

If this package helps you, please give it a ⭐️ on [GitHub](https://github.com/aldiazhar/laravel-invoice)!

---

**Built with ❤️ by Aldiazhar**

*Transform complex invoice management into elegant code.*
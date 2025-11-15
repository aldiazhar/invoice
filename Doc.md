# Aldiazhar Invoice Package - Complete Documentation

## Table of Contents

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Core Concepts](#core-concepts)
4. [Quick Start](#quick-start)
5. [Creating Invoices](#creating-invoices)
6. [Managing Invoices](#managing-invoices)
7. [Querying Invoices](#querying-invoices)
8. [Item Formats](#item-formats)
9. [Callbacks](#callbacks)
10. [Best Practices](#best-practices)

---

## Introduction

Aldiazhar Invoice is a revolutionary Laravel package that simplifies invoice management through:

- **Dual Polymorphic Relationships**: Connect any payer to any invoiceable item
- **Fluent Builder Pattern**: Natural, readable syntax
- **Smart Callbacks**: Automated post-payment actions
- **Comprehensive Features**: Items, taxes, discounts, metadata

### Architecture Overview

```
┌─────────────┐         ┌──────────────┐
│   Payer     │         │ Invoiceable  │
│ (User/Agent)│         │ (TopUp/Fee)  │
└──────┬──────┘         └──────┬───────┘
       │                       │
       └───────┐       ┌───────┘
               │       │
           ┌───▼───────▼───┐
           │    Invoice    │
           │               │
           │  Items        │
           │  Callbacks    │
           │  Metadata     │
           └───────────────┘
```

---

## Installation

### Step 1: Install via Composer

```bash
composer require aldiazhar/laravel-invoice
```

### Step 2: Publish Assets

```bash
# Publish all
php artisan vendor:publish --provider="Aldiazhar\Invoice\InvoiceServiceProvider"

# Or publish individually
php artisan vendor:publish --tag=invoice-config
php artisan vendor:publish --tag=invoice-migrations
php artisan vendor:publish --tag=invoice-views
```

### Step 3: Run Migrations

```bash
php artisan migrate
```

---

## Core Concepts

### 1. Payer (Who Pays)

Any model that can pay invoices. Examples:
- User (customers)
- Agent (service providers)
- Company (businesses)

**Requirements:**
- Implement `Payer` interface
- Use `HasInvoices` trait

### 2. Invoiceable (What Gets Paid)

Any model that can be invoiced. Examples:
- TopUp (credit purchases)
- RegistrationFee (membership fees)
- Service (service charges)
- Order (product orders)

**Requirements:**
- Implement `Invoiceable` interface
- Use `Invoiceable` trait

### 3. Invoice

The central model connecting payers and invoiceables with:
- Line items
- Tax calculations
- Discount management
- Status tracking
- Callback execution

---

## Quick Start

### Setup Models

#### User Model (Payer)

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
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
        return $this->address ?? null;
    }

    public function getPayerMetadata(): array
    {
        return [
            'user_id' => $this->id,
            'account_type' => 'premium',
        ];
    }
}
```

#### TopUp Model (Invoiceable)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Aldiazhar\Invoice\Traits\Invoiceable as InvoiceableTrait;
use Aldiazhar\Invoice\Contracts\Invoiceable;

class TopUp extends Model implements Invoiceable
{
    use InvoiceableTrait;

    protected $fillable = ['amount', 'type', 'status'];

    public function getInvoiceableDescription(): string
    {
        return "Top Up - {$this->type} - {$this->amount}";
    }

    public function getInvoiceableAmount(): float
    {
        return (float) $this->amount;
    }

    public function getInvoiceableMetadata(): array
    {
        return [
            'topup_id' => $this->id,
            'topup_type' => $this->type,
        ];
    }

    public function onInvoicePaid($invoice): void
    {
        // Auto-activate when paid
        $this->update([
            'status' => 'active',
            'activated_at' => now()
        ]);
    }
}
```

---

## Creating Invoices

### Basic Invoice

```php
$user = User::find(1);
$topup = TopUp::create(['amount' => 100, 'type' => 'credit']);

$invoice = $user->invoice()
    ->pay($topup)
    ->item([
        'name' => 'Credit Top Up',
        'price' => 100.00
    ])
    ->create();
```

### Invoice with Multiple Items

```php
$invoice = $user->invoice()
    ->pay($order)
    ->items([
        [
            'name' => 'Product A',
            'description' => 'High quality product',
            'price' => 50.00,
            'quantity' => 2,
            'tax_rate' => 0.1,
            'sku' => 'PRD-A-001'
        ],
        [
            'name' => 'Product B',
            'price' => 30.00,
            'quantity' => 1,
            'tax_rate' => 0.05
        ],
        [
            'name' => 'Shipping Fee',
            'price' => 10.00
        ]
    ])
    ->create();
```

### Invoice with Tax and Discount

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item(['name' => 'Service', 'price' => 100.00])
    ->tax(10.00)        // Add $10 tax
    ->discount(5.00)    // Apply $5 discount
    ->create();
```

### Invoice with Due Date

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item(['name' => 'Service', 'price' => 100.00])
    ->due('+7 days')    // Due in 7 days
    // or
    ->due('2024-12-31') // Specific date
    // or
    ->due(now()->addMonth()) // Carbon instance
    ->create();
```

### Invoice with Metadata

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item(['name' => 'Service', 'price' => 100.00])
    ->meta([
        'payment_gateway' => 'stripe',
        'transaction_id' => 'txn_123456',
        'customer_notes' => 'Priority order'
    ])
    ->create();
```

### Invoice from Invoiceable Side

```php
// Instead of $user->invoice()->pay($topup)
$invoice = $topup->bill()
    ->to($user)
    ->item(['name' => 'Top Up', 'price' => 100.00])
    ->create();

// Or shorter
$invoice = $topup->inv()
    ->by($user)
    ->item(['name' => 'Top Up', 'price' => 100.00])
    ->create();
```

### Invoice via Facade

```php
use Aldiazhar\Invoice\Facades\Invoice;

$invoice = Invoice::create($user, $topup)
    ->item(['name' => 'Service', 'price' => 100.00])
    ->create();
```

---

## Managing Invoices

### Mark as Paid

```php
// Mark invoice as paid (triggers callbacks)
$invoice->markAsPaid();

// Returns: true
// Sets: status = 'paid', paid_at = now()
// Executes: All registered callbacks
```

### Cancel Invoice

```php
// Cancel pending invoice
$invoice->cancel();

// Throws exception if already paid
```

### Mark as Failed

```php
// Mark payment as failed
$invoice->markAsFailed();

// Sets: status = 'failed'
```

### Refund Invoice

```php
// Refund a paid invoice
$invoice->refund();

// Throws exception if not paid
// Sets: status = 'refunded'
```

### Check Status

```php
// Boolean checks
$invoice->isPaid();      // true/false
$invoice->isPending();   // true/false
$invoice->isOverdue();   // true/false

// Status label
$invoice->status_label;  // "Paid", "Pending", etc.

// Formatted total
$invoice->formatted_total; // "USD 105.00"
```

---

## Querying Invoices

### From Payer (User/Agent)

```php
$user = User::find(1);

// Get all invoices
$allInvoices = $user->invoices;

// Get by status
$paidInvoices = $user->paidInvoices()->get();
$pendingInvoices = $user->pendingInvoices()->get();
$failedInvoices = $user->failedInvoices()->get();
$overdueInvoices = $user->overdueInvoices()->get();

// Get totals
$totalPaid = $user->getTotalPaidAmount();
$totalPending = $user->getTotalPendingAmount();
$totalOverdue = $user->getTotalOverdueAmount();

// Get statistics
$stats = $user->getInvoiceStats();
/*
[
    'total_invoices' => 10,
    'paid_invoices' => 7,
    'pending_invoices' => 2,
    'overdue_invoices' => 1,
    'total_paid' => 750.00,
    'total_pending' => 200.00,
    'total_overdue' => 50.00
]
*/

// Check conditions
if ($user->hasPendingInvoices()) {
    // User has pending payments
}

if ($user->hasOverdueInvoices()) {
    // User has overdue payments
}
```

### From Invoiceable (TopUp/Service)

```php
$topup = TopUp::find(1);

// Get all invoices
$invoices = $topup->invoices;

// Get by status
$paidInvoices = $topup->paidInvoices()->get();
$pendingInvoices = $topup->pendingInvoices()->get();

// Get totals
$totalRevenue = $topup->getTotalRevenue();
$pendingRevenue = $topup->getTotalPendingRevenue();

// Get statistics
$stats = $topup->getInvoiceStats();

// Check conditions
if ($topup->hasBeenPaid()) {
    // At least one invoice is paid
}

if ($topup->hasPendingInvoices()) {
    // Has pending invoices
}
```

### Direct Invoice Queries

```php
use Aldiazhar\Invoice\Models\Invoice;

// Scopes
$pending = Invoice::pending()->get();
$paid = Invoice::paid()->get();
$failed = Invoice::failed()->get();
$overdue = Invoice::overdue()->get();

// For specific payer
$userInvoices = Invoice::forPayer($user)->get();

// For specific invoiceable
$topupInvoices = Invoice::forInvoiceable($topup)->get();

// With relationships
$invoice = Invoice::with(['payer', 'invoiceable', 'items'])->find(1);

// Complex queries
$recentPaid = Invoice::paid()
    ->where('paid_at', '>', now()->subMonth())
    ->orderBy('paid_at', 'desc')
    ->get();
```

### Using Facade

```php
use Aldiazhar\Invoice\Facades\Invoice;

// Get invoice
$invoice = Invoice::find(123);

// Get collections
$pending = Invoice::pending();
$paid = Invoice::paid();
$overdue = Invoice::overdue();

// Get statistics
$stats = Invoice::stats();
/*
[
    'total' => 100,
    'pending' => 20,
    'paid' => 75,
    'overdue' => 5,
    'failed' => 0,
    'total_revenue' => 7500.00,
    'pending_revenue' => 2000.00
]
*/
```

---

## Item Formats

### Array Format (Recommended)

```php
$invoice = $user->invoice()
    ->pay($order)
    ->item([
        'name' => 'Product Name',              // Required
        'description' => 'Product details',    // Optional
        'price' => 99.99,                      // Required
        'quantity' => 2,                       // Optional, default: 1
        'tax_rate' => 0.1,                     // Optional, default: 0 (10%)
        'sku' => 'PRD-001',                    // Optional
        'notes' => 'Special instructions'      // Optional
    ])
    ->create();
```

### Traditional Format

```php
// Format: item(name, price, quantity, tax_rate)
$invoice = $user->invoice()
    ->pay($order)
    ->item('Product Name', 99.99, 2, 0.1)
    ->create();
```

### Multiple Items

```php
$invoice = $user->invoice()
    ->pay($order)
    ->items([
        ['name' => 'Item 1', 'price' => 50.00],
        ['name' => 'Item 2', 'price' => 30.00],
        ['name' => 'Item 3', 'price' => 20.00]
    ])
    ->create();
```

### Field Aliases

```php
// These are equivalent
->item(['name' => 'Product'])
->item(['description' => 'Product'])

// These are equivalent
->item(['quantity' => 2])
->item(['qty' => 2])

// These are equivalent
->item(['tax_rate' => 0.1])
->item(['tax' => 0.1])
```

---

## Callbacks

### Single Callback

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item(['name' => 'Service', 'price' => 100.00])
    ->after(function($paidInvoice) {
        // Executed when invoice is paid
        $paidInvoice->payer->notify(new PaymentSuccess($paidInvoice));
    })
    ->create();
```

### Multiple Callbacks

```php
$invoice = $user->invoice()
    ->pay($topup)
    ->item(['name' => 'Service', 'price' => 100.00])
    ->after(function($inv) {
        // Callback 1: Send notification
        Mail::to($inv->payer_email)->send(new InvoicePaid($inv));
    })
    ->after(function($inv) {
        // Callback 2: Clear cache
        Cache::forget('user_stats_' . $inv->payer_id);
    })
    ->after(function($inv) {
        // Callback 3: Webhook
        Http::post('https://api.example.com/webhook', [
            'invoice_id' => $inv->id,
            'status' => 'paid'
        ]);
    })
    ->create();
```

### Callback via Invoiceable

```php
class TopUp extends Model implements Invoiceable
{
    use InvoiceableTrait;

    public function onInvoicePaid($invoice): void
    {
        $this->update(['status' => 'active']);
        $user = $invoice->payer;
        $user->increment('credits', $this->amount);
        $user->notify(new TopUpActivated($this));
    }
}
```

### Callback Configuration

```php
'callbacks' => [
    'enabled' => true,
    'queue' => false,
],
```

---

## Best Practices

### 1. Use Array Format for Items

```php
->item([
    'name' => 'Premium Service',
    'price' => 99.00,
    'sku' => 'SRV-PREM'
])

->item('Premium Service', 99.00)
```

### 2. Always Add SKUs for Products

```php
->item([
    'name' => 'iPhone 15 Pro',
    'price' => 999.00,
    'sku' => 'IPH-15-PRO-256'
])
```

### 3. Use Descriptive Names and Descriptions

```php
->item([
    'name' => 'Web Development',
    'description' => 'Custom website development - 40 hours @ $50/hr',
    'price' => 2000.00
])
```

### 4. Leverage Callbacks

```php
->after(fn($inv) => $service->activate())
->after(fn($inv) => $user->notify(new PaymentSuccess))
```

### 5. Use Metadata for Additional Context

```php
->meta([
    'payment_gateway' => 'stripe',
    'payment_intent_id' => 'pi_123456',
    'customer_ip' => request()->ip(),
    'promo_code' => 'SUMMER2024'
])
```

### 6. Eager Load Relationships

```php
$invoices = Invoice::with(['payer', 'invoiceable', 'items'])->get();

$invoices = Invoice::all();
```

### 7. Use Transactions for Critical Operations

```php
DB::transaction(function() use ($user, $topup) {
    $invoice = $user->invoice()
        ->pay($topup)
        ->item(['name' => 'Service', 'price' => 100.00])
        ->create();
    
    $result = PaymentGateway::charge($invoice);
    
    if ($result->success) {
        $invoice->markAsPaid();
    } else {
        throw new Exception('Payment failed');
    }
});
```

### 8. Handle Overdue Invoices

```php
class CheckOverdueInvoices extends Command
{
    public function handle()
    {
        $overdue = Invoice::overdue()->get();
        
        foreach ($overdue as $invoice) {
            $invoice->payer->notify(new InvoiceOverdue($invoice));
        }
    }
}
```

---

## Complete Examples

### E-Commerce Checkout

```php
public function checkout(Request $request)
{
    $user = auth()->user();
    $cart = $user->cart;
    
    DB::transaction(function() use ($user, $cart, $request) {
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);
        
        $invoice = $user->invoice()
            ->pay($order)
            ->items($cart->items->map(function($item) {
                return [
                    'name' => $item->product->name,
                    'description' => $item->product->description,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'tax_rate' => 0.1,
                    'sku' => $item->product->sku
                ];
            })->toArray())
            ->item([
                'name' => 'Shipping Fee',
                'price' => 10.00
            ])
            ->discount($cart->discount_amount)
            ->meta([
                'payment_method' => $request->payment_method,
                'shipping_address' => $request->shipping_address
            ])
            ->after(function($inv) use ($order) {
                $order->update(['status' => 'paid']);
                $order->ship();
            })
            ->create();
        
        $payment = PaymentGateway::charge($invoice);
        
        if ($payment->success) {
            $invoice->markAsPaid();
            return redirect()->route('orders.show', $order);
        }
        
        throw new Exception('Payment failed');
    });
}
```

### SaaS Subscription Renewal

```php
public function renewSubscription(Subscription $subscription)
{
    $company = $subscription->company;
    
    $invoice = $company->invoice()
        ->pay($subscription)
        ->item([
            'name' => "{$subscription->plan->name} Plan",
            'description' => "Monthly subscription - " . now()->format('F Y'),
            'price' => $subscription->plan->price,
            'sku' => $subscription->plan->sku
        ])
        ->due('+30 days')
        ->after(function($inv) use ($subscription) {
            $subscription->renew();
            $subscription->company->notify(new SubscriptionRenewed($subscription));
        })
        ->create();
    
    try {
        $result = StripeGateway::charge($company->stripe_customer_id, $invoice->total_amount);
        $invoice->markAsPaid();
    } catch (Exception $e) {
        $invoice->markAsFailed();
        $company->notify(new PaymentFailed($invoice));
    }
}
```

---

## Support & Resources

- **Documentation**: [https://docs.aldiazhar-invoice.com](https://docs.aldiazhar-invoice.com)
- **GitHub**: [https://github.com/aldiazhar/laravel-invoice](https://github.com/aldiazhar/laravel-invoice)
- **Issues**: [https://github.com/aldiazhar/laravel-invoice/issues](https://github.com/aldiazhar/laravel-invoice/issues)

---

**Built with ❤️ by Aldiazhar**
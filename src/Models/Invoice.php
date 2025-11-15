<?php

namespace Aldiazhar\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Aldiazhar\Invoice\Exceptions\InvoiceException;
use Carbon\Carbon;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'payer_type',
        'payer_id',
        'payer_name',
        'payer_email',
        'invoiceable_type',
        'invoiceable_id',
        'description',
        'subtotal_amount',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'status',
        'due_date',
        'paid_at',
        'metadata',
        'is_recurring',
        'recurring_frequency',
        'recurring_interval',
        'recurring_end_date',
        'next_billing_date',
        'parent_invoice_id',
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
        'is_recurring' => 'boolean',
        'recurring_end_date' => 'datetime',
        'next_billing_date' => 'datetime',
    ];

    protected $appends = ['status_label', 'formatted_total'];

    public $after_paid_callbacks = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('invoice.tables.invoices', 'invoices'));
    }

    protected static function booted()
    {
        if (!config('invoice.activity_log.enabled', true)) {
            return;
        }

        static::created(function ($invoice) {
            $invoice->logActivity('created', 'Invoice created');
        });

        static::updated(function ($invoice) {
            $changes = $invoice->getChanges();
            
            foreach ($changes as $field => $newValue) {
                if ($field === 'status') {
                    $oldValue = $invoice->getOriginal('status');
                    $invoice->logActivity('status_changed', 
                        "Status changed from '{$oldValue}' to '{$newValue}'",
                        ['field' => 'status', 'old' => $oldValue, 'new' => $newValue]
                    );
                }
                
                if ($field === 'total_amount') {
                    $oldValue = $invoice->getOriginal('total_amount');
                    $invoice->logActivity('amount_changed',
                        "Amount changed from {$oldValue} to {$newValue}",
                        ['field' => 'total_amount', 'old' => $oldValue, 'new' => $newValue]
                    );
                }
            }
        });

        static::deleted(function ($invoice) {
            $invoice->logActivity('deleted', 'Invoice deleted');
        });
    }

    public function payer(): MorphTo
    {
        return $this->morphTo();
    }

    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(InvoiceActivity::class);
    }

    public function parentInvoice()
    {
        return $this->belongsTo(Invoice::class, 'parent_invoice_id');
    }

    public function childInvoices()
    {
        return $this->hasMany(Invoice::class, 'parent_invoice_id');
    }

    public function markAsPaid(): bool
    {
        if ($this->isPaid()) {
            throw InvoiceException::alreadyPaid();
        }

        $this->update([
            'status' => config('invoice.statuses.paid', 'paid'),
            'paid_at' => now(),
        ]);

        $this->executeCallbacks();

        if ($this->invoiceable && method_exists($this->invoiceable, 'onInvoicePaid')) {
            $this->invoiceable->onInvoicePaid($this);
        }

        return true;
    }

    protected function executeCallbacks(): void
    {
        if (!config('invoice.callbacks.enabled', true)) {
            return;
        }

        if (!empty($this->after_paid_callbacks)) {
            foreach ($this->after_paid_callbacks as $callback) {
                if (is_callable($callback)) {
                    try {
                        $callback($this);
                    } catch (\Exception $e) {
                        logger()->error('Invoice paid callback error: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    public function cancel(): bool
    {
        if ($this->isPaid()) {
            throw InvoiceException::cannotCancelPaid();
        }

        return $this->update([
            'status' => config('invoice.statuses.cancelled', 'cancelled'),
        ]);
    }

    public function markAsFailed(): bool
    {
        return $this->update([
            'status' => config('invoice.statuses.failed', 'failed'),
        ]);
    }

    public function refund(): bool
    {
        if (!$this->isPaid()) {
            throw InvoiceException::cannotRefundUnpaid();
        }

        return $this->update([
            'status' => config('invoice.statuses.refunded', 'refunded'),
        ]);
    }

    public function addPayment(float $amount, string $method = 'manual', array $data = []): InvoicePayment
    {
        $remaining = $this->getRemainingAmount();
        
        if ($amount > $remaining) {
            throw InvoiceException::paymentExceedsRemaining($amount, $remaining);
        }
        
        $payment = $this->payments()->create([
            'amount' => $amount,
            'payment_method' => $method,
            'reference_number' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'paid_at' => now(),
        ]);
        
        if ($this->getRemainingAmount() <= 0) {
            $this->markAsPaid();
        }
        
        return $payment;
    }

    public function getPaidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingAmount(): float
    {
        return $this->total_amount - $this->getPaidAmount();
    }

    public function isFullyPaid(): bool
    {
        return $this->getRemainingAmount() <= 0;
    }

    public function generateNextInvoice(): ?Invoice
    {
        if (!$this->is_recurring) {
            return null;
        }
        
        if ($this->recurring_end_date && now()->isAfter($this->recurring_end_date)) {
            return null;
        }
        
        $newInvoice = $this->replicate(['invoice_number', 'paid_at', 'status']);
        $newInvoice->parent_invoice_id = $this->id;
        $newInvoice->due_date = now()->addDays(config('invoice.due_date_days'));
        $newInvoice->save();
        
        foreach ($this->items as $item) {
            $newInvoice->items()->create($item->toArray());
        }
        
        $this->update([
            'next_billing_date' => $this->calculateNextBillingDate(),
        ]);
        
        return $newInvoice;
    }

    protected function calculateNextBillingDate(): Carbon
    {
        return match($this->recurring_frequency) {
            'daily' => $this->next_billing_date->addDays($this->recurring_interval),
            'weekly' => $this->next_billing_date->addWeeks($this->recurring_interval),
            'monthly' => $this->next_billing_date->addMonths($this->recurring_interval),
            'yearly' => $this->next_billing_date->addYears($this->recurring_interval),
            default => $this->next_billing_date->addMonth(),
        };
    }

    public function logActivity(string $action, string $description, array $data = []): void
    {
        $this->activities()->create([
            'action' => $action,
            'description' => $description,
            'old_values' => $data['old'] ?? null,
            'new_values' => $data['new'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'causer_type' => auth()->check() ? get_class(auth()->user()) : null,
            'causer_id' => auth()->id(),
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->status === config('invoice.statuses.pending', 'pending')
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function isPaid(): bool
    {
        return $this->status === config('invoice.statuses.paid', 'paid');
    }

    public function isPending(): bool
    {
        return $this->status === config('invoice.statuses.pending', 'pending');
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    public function getFormattedTotalAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->total_amount, 2);
    }

    public function scopePending($query)
    {
        return $query->where('status', config('invoice.statuses.pending', 'pending'));
    }

    public function scopePaid($query)
    {
        return $query->where('status', config('invoice.statuses.paid', 'paid'));
    }

    public function scopeFailed($query)
    {
        return $query->where('status', config('invoice.statuses.failed', 'failed'));
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', config('invoice.statuses.pending', 'pending'))
                    ->where('due_date', '<', now());
    }

    public function scopeForPayer($query, $payer)
    {
        return $query->where('payer_type', get_class($payer))
                    ->where('payer_id', $payer->id);
    }

    public function scopeForInvoiceable($query, $invoiceable)
    {
        return $query->where('invoiceable_type', get_class($invoiceable))
                    ->where('invoiceable_id', $invoiceable->id);
    }
}
<?php

namespace Aldiazhar\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Aldiazhar\Invoice\Exceptions\InvoiceException;

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
    ];

    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = ['status_label', 'formatted_total'];

    /**
     * Store callbacks in memory (not in database)
     */
    public $pending_callbacks = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('invoice.tables.invoices', 'invoices'));
    }

    /**
     * Get the payer (polymorphic)
     */
    public function payer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the invoiceable (polymorphic)
     */
    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get invoice items
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Mark invoice as paid and execute callbacks
     */
    public function markAsPaid(): bool
    {
        if ($this->isPaid()) {
            throw new InvoiceException('Invoice is already paid.');
        }

        $this->update([
            'status' => config('invoice.statuses.paid', 'paid'),
            'paid_at' => now(),
        ]);

        // Execute callbacks
        $this->executeCallbacks();

        // Trigger invoiceable callback
        if ($this->invoiceable && method_exists($this->invoiceable, 'onInvoicePaid')) {
            $this->invoiceable->onInvoicePaid($this);
        }

        return true;
    }

    /**
     * Execute registered callbacks
     */
    protected function executeCallbacks(): void
    {
        if (!config('invoice.callbacks.enabled', true)) {
            return;
        }

        if (!empty($this->pending_callbacks)) {
            foreach ($this->pending_callbacks as $callback) {
                if (is_callable($callback)) {
                    try {
                        $callback($this);
                    } catch (\Exception $e) {
                        // Log error but don't fail the payment
                        logger()->error('Invoice callback error: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Cancel the invoice
     */
    public function cancel(): bool
    {
        if ($this->isPaid()) {
            throw new InvoiceException('Cannot cancel a paid invoice.');
        }

        return $this->update([
            'status' => config('invoice.statuses.cancelled', 'cancelled'),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(): bool
    {
        return $this->update([
            'status' => config('invoice.statuses.failed', 'failed'),
        ]);
    }

    /**
     * Refund the invoice
     */
    public function refund(): bool
    {
        if (!$this->isPaid()) {
            throw new InvoiceException('Only paid invoices can be refunded.');
        }

        return $this->update([
            'status' => config('invoice.statuses.refunded', 'refunded'),
        ]);
    }

    /**
     * Check if invoice is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === config('invoice.statuses.pending', 'pending')
            && $this->due_date
            && $this->due_date->isPast();
    }

    /**
     * Check if invoice is paid
     */
    public function isPaid(): bool
    {
        return $this->status === config('invoice.statuses.paid', 'paid');
    }

    /**
     * Check if invoice is pending
     */
    public function isPending(): bool
    {
        return $this->status === config('invoice.statuses.pending', 'pending');
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return ucfirst($this->status);
    }

    /**
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        return $this->currency . ' ' . number_format($this->total_amount, 2);
    }

    /**
     * Scope for pending invoices
     */
    public function scopePending($query)
    {
        return $query->where('status', config('invoice.statuses.pending', 'pending'));
    }

    /**
     * Scope for paid invoices
     */
    public function scopePaid($query)
    {
        return $query->where('status', config('invoice.statuses.paid', 'paid'));
    }

    /**
     * Scope for failed invoices
     */
    public function scopeFailed($query)
    {
        return $query->where('status', config('invoice.statuses.failed', 'failed'));
    }

    /**
     * Scope for overdue invoices
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', config('invoice.statuses.pending', 'pending'))
                    ->where('due_date', '<', now());
    }

    /**
     * Scope for specific payer
     */
    public function scopeForPayer($query, $payer)
    {
        return $query->where('payer_type', get_class($payer))
                    ->where('payer_id', $payer->id);
    }

    /**
     * Scope for specific invoiceable
     */
    public function scopeForInvoiceable($query, $invoiceable)
    {
        return $query->where('invoiceable_type', get_class($invoiceable))
                    ->where('invoiceable_id', $invoiceable->id);
    }
}
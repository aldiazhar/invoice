<?php

namespace Aldiazhar\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'name',
        'description',
        'price',
        'quantity',
        'tax_rate',
        'subtotal',
        'notes',
        'sku',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'tax_rate' => 'decimal:4',
        'subtotal' => 'decimal:2',
    ];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('invoice.tables.invoice_items', 'invoice_items'));
    }

    /**
     * Get the invoice that owns the item
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get tax amount for this item
     */
    public function getTaxAmountAttribute(): float
    {
        return $this->subtotal * $this->tax_rate;
    }

    /**
     * Get total amount including tax
     */
    public function getTotalAttribute(): float
    {
        return $this->subtotal + $this->tax_amount;
    }
}
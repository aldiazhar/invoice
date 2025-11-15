<?php

namespace Aldiazhar\Invoice\Contracts;

/**
 * Interface for models that can be invoiced
 * (TopUp, Registration, Service, Order, etc.)
 */
interface Invoiceable
{
    /**
     * Get the invoiceable description
     */
    public function getInvoiceableDescription(): string;

    /**
     * Get the amount to be invoiced
     */
    public function getInvoiceableAmount(): float;

    /**
     * Get additional invoiceable metadata (optional)
     */
    public function getInvoiceableMetadata(): array;

    /**
     * Callback when invoice is paid (optional)
     */
    public function onInvoicePaid($invoice): void;
}
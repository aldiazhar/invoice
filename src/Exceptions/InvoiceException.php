<?php

namespace Aldiazhar\Invoice\Exceptions;

use Exception;

class InvoiceException extends Exception
{
    public static function payerRequired(): self
    {
        return new self('Payer is required to create an invoice');
    }

    public static function invoiceableRequired(): self
    {
        return new self('Invoiceable is required to create an invoice');
    }

    public static function itemsRequired(): self
    {
        return new self('At least one item is required to create an invoice');
    }

    public static function negativePrice(string $itemName, float $price): self
    {
        return new self("Item '{$itemName}' has negative price: {$price}");
    }

    public static function invalidQuantity(string $itemName): self
    {
        return new self("Item '{$itemName}' must have quantity of at least 1");
    }

    public static function invalidTaxRate(string $itemName, float $taxRate): self
    {
        return new self("Item '{$itemName}' has invalid tax rate: {$taxRate} (must be between 0 and 1)");
    }

    public static function amountMismatch(float $expected, float $calculated): self
    {
        return new self(
            "Invoice total mismatch! Expected: " . number_format($expected, 2) . 
            ", Calculated: " . number_format($calculated, 2) . 
            ". Use withoutStrictValidation() if intentional."
        );
    }

    public static function discountExceedsSubtotal(float $discount, float $subtotal): self
    {
        return new self(
            "Discount (" . number_format($discount, 2) . 
            ") cannot exceed subtotal (" . number_format($subtotal, 2) . ")"
        );
    }

    public static function negativeTotalAmount(float $subtotal, float $tax, float $discount): self
    {
        return new self(
            "Total cannot be negative. Subtotal: {$subtotal}, Tax: {$tax}, Discount: {$discount}"
        );
    }

    public static function alreadyPaid(): self
    {
        return new self('Invoice is already paid');
    }

    public static function cannotCancelPaid(): self
    {
        return new self('Cannot cancel a paid invoice');
    }

    public static function cannotRefundUnpaid(): self
    {
        return new self('Only paid invoices can be refunded');
    }

    public static function paymentExceedsRemaining(float $payment, float $remaining): self
    {
        return new self(
            "Payment (" . number_format($payment, 2) . 
            ") exceeds remaining amount (" . number_format($remaining, 2) . ")"
        );
    }
}
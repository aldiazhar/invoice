<?php

namespace Aldiazhar\Invoice\Contracts;

interface Invoiceable
{
    public function getInvoiceableDescription(): string;
    public function getInvoiceableAmount(): float;
    public function getInvoiceableMetadata(): array;
    public function onInvoicePaid($invoice): void;
}
<?php

namespace Aldiazhar\Invoice\Contracts;

/**
 * Interface for models that can pay invoices
 * (User, Agent, Company, etc.)
 */
interface Payer
{
    /**
     * Get the payer's name for invoice
     */
    public function getPayerName(): string;

    /**
     * Get the payer's email for invoice
     */
    public function getPayerEmail(): ?string;

    /**
     * Get the payer's address (optional)
     */
    public function getPayerAddress(): ?string;

    /**
     * Get additional payer information (optional)
     */
    public function getPayerMetadata(): array;
}
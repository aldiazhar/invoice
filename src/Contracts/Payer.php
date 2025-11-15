<?php

namespace Aldiazhar\Invoice\Contracts;

interface Payer
{
    public function getPayerName(): string;
    public function getPayerEmail(): ?string;
    public function getPayerAddress(): ?string;
    public function getPayerMetadata(): array;
}
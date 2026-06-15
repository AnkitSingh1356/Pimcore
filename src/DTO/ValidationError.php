<?php

namespace App\DTO;

class ValidationError
{
    public function __construct(
        public readonly int    $rowNumber,
        public readonly string $sku,
        public readonly string $fieldName,
        public readonly string $invalidValue,
        public readonly string $errorCode,
        public readonly string $errorMessage,
    ) {}
}

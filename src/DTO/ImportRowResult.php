<?php

namespace App\DTO;

class ImportRowResult
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED  = 'FAILED';

    
    public function __construct(
        public readonly int    $rowNumber,
        public readonly string $sku,
        public readonly string $status,
        public readonly array  $errors    = [],
        public readonly ?int   $customerId = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }
}

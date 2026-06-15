<?php

namespace App\Service\CustomerImport;

class CustomerImportConfig
{
    private int    $batchSize  = 500;
    private string $importMode = 'create_or_update';

    private array $requiredHeaders = [
        'first_name', 'last_name', 'email', 'phone',
        'city', 'country', 'preferred_sport', 'customer_type',
    ];

    private array $fieldRules = [
        'first_name'       => ['type' => 'string',  'required' => true,  'max_length' => 190],
        'last_name'        => ['type' => 'string',  'required' => true,  'max_length' => 190],
        'email'            => ['type' => 'email',   'required' => true,  'max_length' => 190, 'unique' => true],
        'phone'            => ['type' => 'string',  'required' => true,  'max_length' => 190, 'format' => 'e164_or_local'],
        'city'             => ['type' => 'string',  'required' => true,  'max_length' => 100],
        'country'          => ['type' => 'string',  'required' => true,  'max_length' => 100],
        'channel'          => ['type' => 'enum',    'required' => false,
            'allowed_values' => ['web', 'mobile', 'retail', 'wholesale', 'partner', 'other']],
        'customer_type'    => ['type' => 'enum',    'required' => true,
            'allowed_values' => ['individual', 'business', 'vip', 'partner']],
        'preferred_sport'  => [
            'type' => 'enum',
            'required' => true,
            'is_list' => true,
            'allowed_values' => [
                'football', 'basketball', 'tennis', 'golf', 'cycling',
                'running', 'swimming', 'fitness', 'cricket', 'volleyball',
                'badminton', 'table_tennis', 'boxing', 'yoga', 'other',
            ],
        ],
        'newsletter_optin' => ['type' => 'boolean', 'required' => false],
        'date_of_birth'    => ['type' => 'date',    'required' => false,
            'formats' => ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd.m.Y']],
        'gender'           => ['type' => 'string',  'required' => false, 'max_length' => 190],
        'address'          => ['type' => 'string',  'required' => false, 'max_length' => 190],
        'status'           => ['type' => 'string',  'required' => false, 'max_length' => 190],
    ];

    public function getBatchSize(): int                      { return $this->batchSize; }
    public function getImportMode(): string                  { return $this->importMode; }
    public function getRequiredHeaders(): array              { return $this->requiredHeaders; }
    public function getFieldRules(): array                   { return $this->fieldRules; }
    public function getFieldRule(string $f): ?array          { return $this->fieldRules[$f] ?? null; }
    public function isCreateAllowed(): bool                  { return in_array($this->importMode, ['create_only', 'create_or_update'], true); }
    public function isUpdateAllowed(): bool                  { return in_array($this->importMode, ['update_only', 'create_or_update'], true); }

    public function setImportMode(string $mode): static
    {
        if (!in_array($mode, ['create_only', 'update_only', 'create_or_update'], true)) {
            throw new \InvalidArgumentException("Invalid import mode: $mode");
        }
        $this->importMode = $mode;
        return $this;
    }
}

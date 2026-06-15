<?php

namespace App\Service\CustomerImport;

use App\DTO\ValidationError;
use Doctrine\DBAL\Connection;

class CustomerValidationEngine
{
    private const ERR_INVALID_PHONE     = 'INVALID_PHONE_FORMAT';

    
    public const ERR_REQUIRED           = 'REQUIRED_FIELD_MISSING';
    public const ERR_INVALID_EMAIL      = 'INVALID_EMAIL_FORMAT';
    public const ERR_MAX_LENGTH         = 'MAX_LENGTH_EXCEEDED';
    public const ERR_INVALID_ENUM       = 'INVALID_ENUM_VALUE';
    public const ERR_INVALID_DATE       = 'INVALID_DATE_FORMAT';
    public const ERR_INVALID_BOOLEAN    = 'INVALID_BOOLEAN_VALUE';
    public const ERR_DUPLICATE_IN_FILE  = 'DUPLICATE_EMAIL_IN_FILE';
    public const ERR_DUPLICATE_IN_DB    = 'DUPLICATE_EMAIL_IN_SYSTEM';
    public const ERR_CREATE_NOT_ALLOWED = 'CREATE_NOT_ALLOWED_IN_MODE';
    public const ERR_UPDATE_NOT_ALLOWED = 'UPDATE_NOT_ALLOWED_IN_MODE';

    public function __construct(
        private readonly CustomerImportConfig $config,
        private readonly Connection           $db,
    ) {}

    

    

    public function validateRow(array $row, int $rowNumber, array $seenEmails): array
    {
        $email  = strtolower(trim($row['email'] ?? ''));
        $errors = array_merge(
            $this->validateRequiredFields($row, $rowNumber, $email),
            $this->validateFieldTypes($row, $rowNumber, $email),
            $this->validateBusinessRules($row, $rowNumber, $email, $seenEmails),
        );

        return $errors;
    }

    

    
    private function validateRequiredFields(array $row, int $rowNumber, string $email): array
    {
        $errors = [];
        foreach ($this->config->getFieldRules() as $field => $rules) {
            if (!($rules['required'] ?? false)) {
                continue;
            }
            if (trim($row[$field] ?? '') === '') {
                $errors[] = new ValidationError(
                    rowNumber:    $rowNumber,
                    sku:          $email,
                    fieldName:    $field,
                    invalidValue: '',
                    errorCode:    self::ERR_REQUIRED,
                    errorMessage: sprintf('"%s" is required and cannot be empty.', $field),
                );
            }
        }
        return $errors;
    }

    

    
    private function validateFieldTypes(array $row, int $rowNumber, string $email): array
    {
        $errors = [];
        foreach ($this->config->getFieldRules() as $field => $rules) {
            $value = trim($row[$field] ?? '');

            
            if ($value === '') {
                continue;
            }

            $fieldErrors = match ($rules['type']) {
                'email'   => $this->validateEmailField($field, $value, $rowNumber, $email),
                'enum'    => $this->validateEnum($field, $value, $rowNumber, $email, $rules),
                'date'    => $this->validateDate($field, $value, $rowNumber, $email, $rules),
                'boolean' => $this->validateBoolean($field, $value, $rowNumber, $email),
                default   => $this->validateString($field, $value, $rowNumber, $email, $rules),
            };
            $errors = array_merge($errors, $fieldErrors);

            
            if (empty($fieldErrors) && ($format = ($rules['format'] ?? null)) !== null) {
                $errors = array_merge($errors, $this->validateFormatField($field, $value, $rowNumber, $email, $format));
            }
        }
        return $errors;
    }

    
    private function validateEmailField(
        string $field, string $value, int $rowNumber, string $email
    ): array {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return [new ValidationError(
                rowNumber:    $rowNumber,
                sku:          $email,
                fieldName:    $field,
                invalidValue: $value,
                errorCode:    self::ERR_INVALID_EMAIL,
                errorMessage: sprintf('"%s" is not a valid email address.', $value),
            )];
        }
        
        $rules = $this->config->getFieldRule($field) ?? [];
        return $this->validateString($field, $value, $rowNumber, $email, $rules);
    }

    
    private function validateEnum(
        string $field,
        string $value,
        int $rowNumber,
        string $email,
        array $rules
    ): array {
        $allowed = $rules['allowed_values'] ?? [];

        
        
        if (($rules['is_list'] ?? false) === true) {
            $parts = array_values(array_filter(array_map(
                static fn(string $v) => strtolower(trim($v)),
                explode(',', $value)
            ), static fn(string $v) => $v !== ''));

            if (empty($parts)) {
                return [new ValidationError(
                    rowNumber:    $rowNumber,
                    sku:          $email,
                    fieldName:    $field,
                    invalidValue: $value,
                    errorCode:    self::ERR_INVALID_ENUM,
                    errorMessage: sprintf(
                        '"%s" is not valid for "%s". Allowed: %s.',
                        $value, $field, implode(', ', $allowed)
                    ),
                )];
            }

            $allowedLower = array_map('strtolower', $allowed);
            foreach ($parts as $part) {
                if (!in_array($part, $allowedLower, true)) {
                    return [new ValidationError(
                        rowNumber:    $rowNumber,
                        sku:          $email,
                        fieldName:    $field,
                        invalidValue: $value,
                        errorCode:    self::ERR_INVALID_ENUM,
                        errorMessage: sprintf(
                            '"%s" is not valid for "%s". Allowed: %s.',
                            $part, $field, implode(', ', $allowed)
                        ),
                    )];
                }
            }

            return [];
        }

        
        if (!in_array(strtolower($value), array_map('strtolower', $allowed), true)) {
            return [new ValidationError(
                rowNumber:    $rowNumber,
                sku:          $email,
                fieldName:    $field,
                invalidValue: $value,
                errorCode:    self::ERR_INVALID_ENUM,
                errorMessage: sprintf(
                    '"%s" is not valid for "%s". Allowed: %s.',
                    $value, $field, implode(', ', $allowed)
                ),
            )];
        }

        return [];
    }

    
    private function validateDate(
        string $field, string $value, int $rowNumber, string $email, array $rules
    ): array {
        $formats = $rules['formats'] ?? ['Y-m-d'];
        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $value);
            if ($d && $d->format($fmt) === $value) {
                return [];
            }
        }
        return [new ValidationError(
            rowNumber:    $rowNumber,
            sku:          $email,
            fieldName:    $field,
            invalidValue: $value,
            errorCode:    self::ERR_INVALID_DATE,
            errorMessage: sprintf(
                '"%s" is not a valid date for "%s". Expected formats: %s.',
                $value, $field, implode(', ', $formats)
            ),
        )];
    }

    
    private function validateBoolean(
        string $field, string $value, int $rowNumber, string $email
    ): array {
        $valid = ['1', '0', 'true', 'false', 'yes', 'no', 'on', 'off'];
        if (!in_array(strtolower(trim($value)), $valid, true)) {
            return [new ValidationError(
                rowNumber:    $rowNumber,
                sku:          $email,
                fieldName:    $field,
                invalidValue: $value,
                errorCode:    self::ERR_INVALID_BOOLEAN,
                errorMessage: sprintf(
                    '"%s" is not a valid boolean for "%s". Expected: 1, 0, true, false, yes, no.',
                    $value, $field
                ),
            )];
        }
        return [];
    }

    
    private function validateString(
        string $field, string $value, int $rowNumber, string $email, array $rules
    ): array {
        if (isset($rules['max_length']) && mb_strlen($value) > (int) $rules['max_length']) {
            return [new ValidationError(
                rowNumber:    $rowNumber,
                sku:          $email,
                fieldName:    $field,
                invalidValue: mb_substr($value, 0, 50) . '…',
                errorCode:    self::ERR_MAX_LENGTH,
                errorMessage: sprintf(
                    '"%s" exceeds max length of %d chars (%d provided).',
                    $field, $rules['max_length'], mb_strlen($value)
                ),
            )];
        }
        return [];
    }

    

    
    private function validateBusinessRules(
        array $row, int $rowNumber, string $email, array $seenEmails
    ): array {
        $errors = [];

        if ($email === '') {
            return $errors;
        }

        
        if (in_array($email, array_map('strtolower', $seenEmails), true)) {
            $errors[] = new ValidationError(
                rowNumber:    $rowNumber,
                sku:          $email,
                fieldName:    'email',
                invalidValue: $email,
                errorCode:    self::ERR_DUPLICATE_IN_FILE,
                errorMessage: sprintf('Email "%s" appears more than once in this import file.', $email),
            );
            return $errors;
        }

        $existsInDb = $this->emailExistsInDb($email);

        if ($existsInDb && !$this->config->isUpdateAllowed()) {
            $errors[] = new ValidationError(
                rowNumber:    $rowNumber,
                sku:          $email,
                fieldName:    'email',
                invalidValue: $email,
                errorCode:    self::ERR_UPDATE_NOT_ALLOWED,
                errorMessage: sprintf(
                    'Email "%s" already exists and the import mode (%s) does not allow updates.',
                    $email, $this->config->getImportMode()
                ),
            );
        }

        if (!$existsInDb && !$this->config->isCreateAllowed()) {
            $errors[] = new ValidationError(
                rowNumber:    $rowNumber,
                sku:          $email,
                fieldName:    'email',
                invalidValue: $email,
                errorCode:    self::ERR_CREATE_NOT_ALLOWED,
                errorMessage: sprintf(
                    'Email "%s" does not exist and the import mode (%s) does not allow new records.',
                    $email, $this->config->getImportMode()
                ),
            );
        }

        return $errors;
    }

    private function validateFormatField(
        string $field,
        string $value,
        int $rowNumber,
        string $email,
        string $format
    ): array {
        return match ($format) {
            'e164_or_local' => $this->validatePhoneFormat($field, $value, $rowNumber, $email),
            default => [],
        };
    }

    private function validatePhoneFormat(
        string $field,
        string $value,
        int $rowNumber,
        string $email
    ): array {
        
        
        
        $v = trim($value);

        $digitsOnlyCount = preg_match_all('/\d/', $v);
        $hasEnoughDigits = is_int($digitsOnlyCount) && $digitsOnlyCount >= 7;

        $e164 = preg_match('/^\+[1-9]\d{6,14}$/', $v) === 1;
        if ($e164) {
            return [];
        }

        $localAllowedChars = preg_match('/^[0-9\s\-\(\)\.]+$/', $v) === 1;
        if ($localAllowedChars && $hasEnoughDigits) {
            return [];
        }

        return [new ValidationError(
            rowNumber: $rowNumber,
            sku: $email,
            fieldName: $field,
            invalidValue: $value,
            errorCode: self::ERR_INVALID_PHONE,
            errorMessage: sprintf('"%s" is not a valid phone number format.', $value),
        )];
    }

    private function emailExistsInDb(string $email): bool
    {
        
        
        
        
        
        try {
            
            
            
            $count = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM object_store_1 WHERE `email` = ?',
                [$email]
            );

            return $count > 0;
        } catch (\Throwable) {
            
            try {
                $count = (int) $this->db->fetchOne(
                    'SELECT COUNT(*) FROM customer_import_email_index WHERE email = ?',
                    [strtolower($email)]
                );
                return $count > 0;
            } catch (\Throwable) {
                return false;
            }
        }
    }
}

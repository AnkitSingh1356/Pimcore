<?php

namespace App\Service\CustomerImport;

use Carbon\Carbon;

class CustomerMapper
{
    

    public function mapRowToCustomerData(array $row): array
    {
        return [
            'firstName'       => $this->sanitize($row['first_name']    ?? ''),
            'lastName'        => $this->sanitize($row['last_name']      ?? ''),
            'email'           => strtolower(trim($row['email']          ?? '')),
            'phone'           => $this->sanitize($row['phone']          ?? ''),
            'dateOfBirth'     => $this->parseDate($row['date_of_birth'] ?? ''),
            'gender'          => strtolower(trim($row['gender']         ?? '')),
            'address'         => $this->sanitize($row['address']        ?? ''),
            'status'          => strtolower(trim($row['status']         ?? '')) ?: 'active',
            'city'            => $this->sanitize($row['city']           ?? ''),
            'country'         => $this->sanitize($row['country']        ?? ''),
            'channel'         => strtolower(trim($row['channel']        ?? '')),
            'customerType'    => strtolower(trim($row['customer_type']  ?? '')),
            'preferredSport'  => $this->parsePreferredSport($row['preferred_sport'] ?? ''),
            'newsletterOptin' => $this->parseBoolean($row['newsletter_optin'] ?? ''),
        ];
    }

    

    public function buildObjectKey(string $email): string
    {
        $key = preg_replace('/[^A-Za-z0-9_\-.]/', '-', $email);
        return strtolower(trim((string) $key, '-'));
    }

    

    private function sanitize(string $value): string
    {
        
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        return trim((string) $value);
    }

    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'd.m.Y'];
        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, trim($value));
            if ($d && $d->format($fmt) === trim($value)) {
                return Carbon::instance($d);
            }
        }

        
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseBoolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    

    private function parsePreferredSport(string $value): array
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return [];
        }

        $parts = array_map(
            static fn(string $v) => trim($v),
            explode(',', $value)
        );

        $parts = array_values(array_filter($parts, static fn(string $v) => $v !== ''));
        return $parts;
    }
}

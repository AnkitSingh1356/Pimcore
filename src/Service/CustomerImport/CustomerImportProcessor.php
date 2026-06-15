<?php

namespace App\Service\CustomerImport;

use App\DTO\ImportRowResult;
use App\DTO\ValidationError;
use Doctrine\DBAL\Connection;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\Customer;
use Pimcore\Model\Element\Service;
use Psr\Log\LoggerInterface;

class CustomerImportProcessor
{
    private const CUSTOMERS_FOLDER_PATH = '/customers';

    private ?DataObject\AbstractObject $customersFolder = null;

    public function __construct(
        private readonly CustomerValidationEngine $validationEngine,
        private readonly CustomerMapper           $mapper,
        private readonly Connection               $db,
        private readonly LoggerInterface          $logger,
    ) {}

    

    

    public function processRow(array $row, int $rowNumber, array $seenEmails): ImportRowResult
    {
        $email = strtolower(trim($row['email'] ?? ''));

        try {
            $errors = $this->validationEngine->validateRow($row, $rowNumber, $seenEmails);

            if (!empty($errors)) {
                return new ImportRowResult(
                    rowNumber: $rowNumber,
                    sku:       $email,
                    status:    ImportRowResult::STATUS_FAILED,
                    errors:    $errors,
                );
            }

            $data       = $this->mapper->mapRowToCustomerData($row);
            $customerId = $this->persistCustomer($data);

            $this->logger->debug('CustomerImportProcessor: row saved', [
                'row'         => $rowNumber,
                'email'       => $email,
                'customer_id' => $customerId,
            ]);

            return new ImportRowResult(
                rowNumber: $rowNumber,
                sku:       $email,
                status:    ImportRowResult::STATUS_SUCCESS,
                customerId: $customerId,
            );

        } catch (\Throwable $e) {
            $this->logger->error('CustomerImportProcessor: unexpected error', [
                'row'   => $rowNumber,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return new ImportRowResult(
                rowNumber: $rowNumber,
                sku:       $email,
                status:    ImportRowResult::STATUS_FAILED,
                errors:    [
                    new ValidationError(
                        rowNumber:    $rowNumber,
                        sku:          $email,
                        fieldName:    '_system',
                        invalidValue: '',
                        errorCode:    'SYSTEM_ERROR',
                        errorMessage: 'Unexpected error: ' . $e->getMessage(),
                    ),
                ],
            );
        }
    }

    

    private function persistCustomer(array $data): int
    {
        $email    = $data['email'];
        $customer = $this->findExistingCustomer($email);
        $isNew    = ($customer === null);

        if ($isNew) {
            $customer = new Customer();
            $customer->setParent($this->getCustomersFolder());
            $key = $this->mapper->buildObjectKey($email);
            $customer->setKey(Service::getValidKey($key . '-' . substr(md5($email), 0, 6), 'object'));
            $customer->setPublished(true);
        }

        $customer->setFirstName($data['firstName']);

        if ($data['lastName'] !== '') {
            $customer->setLastName($data['lastName']);
        }

        $customer->setEmail($email);

        if ($data['phone'] !== '') {
            $customer->setPhone($data['phone']);
        }
        if ($data['dateOfBirth'] !== null) {
            $customer->setDateOfBirth($data['dateOfBirth']);
        }
        if ($data['gender'] !== '') {
            $customer->setGender($data['gender']);
        }
        if ($data['address'] !== '') {
            $customer->setAddress($data['address']);
        }

        $customer->setStatus($data['status'] ?: 'active');

        
        if ($data['city'] !== '') {
            $customer->setCity($data['city']);
        }
        if ($data['country'] !== '') {
            $customer->setCountry($data['country']);
        }
        if ($data['channel'] !== '') {
            $customer->setChannel($data['channel']);
        }
        if ($data['customerType'] !== '') {
            $customer->setCustomerType($data['customerType']);
        }
        if (!empty($data['preferredSport'])) {
            
            
            
            $sports = $data['preferredSport'];
            $customer->setPreferredSport(
                is_array($sports) ? implode(',', $sports) : (string) $sports
            );
        }

        $customer->setNewsletterOptin($data['newsletterOptin']);

        $customer->save();

        $this->upsertEmailIndex($email, $customer->getId());

        return $customer->getId();
    }

    private function findExistingCustomer(string $email): ?Customer
    {
        
        try {
            $pimcoreId = $this->db->fetchOne(
                'SELECT pimcore_id FROM customer_import_email_index WHERE email = ?',
                [strtolower($email)]
            );
            if ($pimcoreId) {
                $c = Customer::getById((int) $pimcoreId);
                return $c instanceof Customer ? $c : null;
            }
        } catch (\Throwable) {
            
        }

        
        $listing = new Customer\Listing();
        $listing->setCondition('email = ?', [$email]);
        $listing->setLimit(1);
        $listing->load();

        $objects = $listing->getObjects();
        return !empty($objects) ? $objects[0] : null;
    }

    private function upsertEmailIndex(string $email, int $pimcoreId): void
    {
        try {
            $existing = $this->db->fetchOne(
                'SELECT id FROM customer_import_email_index WHERE email = ?',
                [strtolower($email)]
            );
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            if ($existing) {
                $this->db->update(
                    'customer_import_email_index',
                    ['pimcore_id' => $pimcoreId, 'updated_at' => $now],
                    ['email' => strtolower($email)]
                );
            } else {
                $this->db->insert('customer_import_email_index', [
                    'email'      => strtolower($email),
                    'pimcore_id' => $pimcoreId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('CustomerImportProcessor: emailIndex upsert failed', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    

    private function getCustomersFolder(): DataObject\AbstractObject
    {
        if ($this->customersFolder === null) {
            $folder = DataObject::getByPath(self::CUSTOMERS_FOLDER_PATH);
            if (!$folder instanceof DataObject\Folder) {
                
                $folder = new DataObject\Folder();
                $folder->setParent(DataObject::getByPath('/'));
                $folder->setKey('customers');
                $folder->save();
            }
            $this->customersFolder = $folder;
        }
        return $this->customersFolder;
    }
}

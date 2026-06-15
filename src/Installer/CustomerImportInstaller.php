<?php

namespace App\Installer;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class CustomerImportInstaller
{
    public function __construct(
        private readonly Connection      $db,
        private readonly LoggerInterface $logger,
    ) {}

    public function install(): void
    {
        $this->logger->info('CustomerImportInstaller: starting');

        $this->createLogsTable();
        $this->createErrorsTable();
        $this->createEmailIndexTable();
        $this->migrateCustomerDataObjectColumns();
        $this->renameRowNumberColumn();

        $this->logger->info('CustomerImportInstaller: done');
    }

    public function isInstalled(): bool
    {
        return $this->tableExists('customer_import_logs')
            && $this->tableExists('customer_import_errors')
            && $this->tableExists('customer_import_email_index');
    }

    private function createLogsTable(): void
    {
        if ($this->tableExists('customer_import_logs')) {
            return;
        }
        $this->db->executeStatement('
            CREATE TABLE IF NOT EXISTS `customer_import_logs` (
                `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `import_id`           VARCHAR(100) NOT NULL,
                `file_name`           VARCHAR(255) NOT NULL,
                `file_size_bytes`     INT UNSIGNED NOT NULL DEFAULT 0,
                `file_hash`           VARCHAR(64)  NULL,
                `uploaded_by`         VARCHAR(255) NOT NULL DEFAULT \'admin\',
                `upload_timestamp`    DATETIME     NOT NULL,
                `processing_start`    DATETIME     NULL,
                `processing_end`      DATETIME     NULL,
                `status`              VARCHAR(50)  NOT NULL DEFAULT \'PENDING\',
                `total_records`       INT UNSIGNED NOT NULL DEFAULT 0,
                `successful_records`  INT UNSIGNED NOT NULL DEFAULT 0,
                `failed_records`      INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_import_id` (`import_id`),
                KEY `idx_status`     (`status`),
                KEY `idx_file_hash`  (`file_hash`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $this->logger->info('CustomerImportInstaller: created customer_import_logs');
    }

    private function createErrorsTable(): void
    {
        if ($this->tableExists('customer_import_errors')) {
            return;
        }
        $this->db->executeStatement('
            CREATE TABLE IF NOT EXISTS `customer_import_errors` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `import_id`     VARCHAR(100) NOT NULL,
                `row_num`       INT UNSIGNED NOT NULL,
                `email`         VARCHAR(255) NOT NULL DEFAULT \'\',
                `field_name`    VARCHAR(100) NOT NULL,
                `invalid_value` TEXT         NULL,
                `error_code`    VARCHAR(100) NOT NULL,
                `error_message` TEXT         NOT NULL,
                `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_import_id`  (`import_id`),
                KEY `idx_email`      (`email`),
                KEY `idx_row`        (`import_id`, `row_num`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $this->logger->info('CustomerImportInstaller: created customer_import_errors');
        $this->renameRowNumberColumn();
    }

    private function createEmailIndexTable(): void
    {
        if ($this->tableExists('customer_import_email_index')) {
            return;
        }
        $this->db->executeStatement('
            CREATE TABLE IF NOT EXISTS `customer_import_email_index` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `email`       VARCHAR(255) NOT NULL,
                `pimcore_id`  INT UNSIGNED NOT NULL,
                `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_email`      (`email`),
                KEY         `idx_pimcore`  (`pimcore_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
        $this->logger->info('CustomerImportInstaller: created customer_import_email_index');
    }

    private function migrateCustomerDataObjectColumns(): void
    {
        $newColumns = [
            'city'           => 'VARCHAR(100) NULL DEFAULT NULL',
            'country'        => 'VARCHAR(100) NULL DEFAULT NULL',
            'channel'        => 'VARCHAR(190) NULL DEFAULT NULL',
            'customerType'   => 'VARCHAR(190) NULL DEFAULT NULL',
            'preferredSport' => 'VARCHAR(190) NULL DEFAULT NULL',
            'newsletterOptin'=> 'TINYINT(1)   NULL DEFAULT 0',
        ];

        foreach (['object_store_1', 'object_query_1'] as $table) {
            if (!$this->tableExists($table)) {
                continue;
            }
            foreach ($newColumns as $column => $definition) {
                $this->addColumnIfNotExists($table, $column, $definition);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $result = $this->db->fetchOne(
                'SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            return (int) $result > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        try {
            $exists = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column]
            );
            if ($exists === 0) {
                $this->db->executeStatement(
                    "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}"
                );
                $this->logger->info("CustomerImportInstaller: added column {$table}.{$column}");
            }
        } catch (\Throwable $e) {
            $this->logger->warning("CustomerImportInstaller: addColumn failed for {$table}.{$column}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function renameRowNumberColumn(): void
    {
        try {
            $oldExists = (int) $this->db->fetchOne(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = ?
                    AND COLUMN_NAME  = ?',
                ['customer_import_errors', 'row_number']
            );
            if ($oldExists > 0) {
                $this->db->executeStatement(
                    'ALTER TABLE `customer_import_errors`
                     CHANGE COLUMN `row_number` `row_num` INT UNSIGNED NOT NULL'
                );
                
                $idxExists = (int) $this->db->fetchOne(
                    "SELECT COUNT(*) FROM information_schema.STATISTICS
                      WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME   = 'customer_import_errors'
                        AND INDEX_NAME   = 'idx_row'"
                );
                if ($idxExists > 0) {
                    $this->db->executeStatement(
                        'ALTER TABLE `customer_import_errors`
                         DROP INDEX `idx_row`,
                         ADD  INDEX `idx_row` (`import_id`, `row_num`)'
                    );
                }
                $this->logger->info('CustomerImportInstaller: renamed row_number → row_num in customer_import_errors');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('CustomerImportInstaller: renameRowNumberColumn failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

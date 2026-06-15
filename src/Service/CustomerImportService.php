<?php

namespace App\Service;

use App\DTO\ValidationError;
use App\Service\CustomerImport\CsvReader;
use App\Service\CustomerImport\CustomerImportConfig;
use App\Service\CustomerImport\CustomerImportProcessor;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class CustomerImportService
{
    public function __construct(
        private readonly CsvReader               $csvReader,
        private readonly CustomerImportProcessor $processor,
        private readonly CustomerImportConfig    $config,
        private readonly Connection              $db,
        private readonly LoggerInterface         $logger,
    ) {}

    private const OUTPUT_STATUS_COLUMN = 'status';

    

    

    public function importFromFile(string $filePath, string $importedBy = 'cli'): array
    {
        $startTime = microtime(true);
        $fileName  = basename($filePath);
        $fileExists = file_exists($filePath);
        $fileSize  = $fileExists ? (int) filesize($filePath) : 0;
        $fileHash  = $fileExists ? ((string) md5_file($filePath)) : '';

        
        if ($fileHash !== '' && $this->isDuplicateFile($fileHash)) {
            $this->logger->info('CustomerImportService: duplicate file skipped', [
                'file' => $filePath,
                'hash' => $fileHash,
            ]);
            return $this->buildNoChangeResult($fileName, $startTime);
        }

        $importId = $this->generateImportId();

        $this->logger->info('CustomerImportService: import started', [
            'import_id' => $importId,
            'file'      => $filePath,
            'user'      => $importedBy,
        ]);

        $this->createAuditLog($importId, $fileName, $importedBy, $fileSize, $fileHash);

        
        $fileErrors = $this->csvReader->validateFile($filePath);
        if (!empty($fileErrors)) {
            $this->updateAuditStatus($importId, 'HEADER_VALIDATION_FAILED');
            return $this->buildResult($importId, $fileName, 0, 0, 0, [], $fileErrors, [], $startTime, []);
        }

        
        $headerResult = $this->csvReader->validateHeaders($filePath, $this->config->getRequiredHeaders());
        if (!empty($headerResult['errors'])) {
            $this->updateAuditStatus($importId, 'HEADER_VALIDATION_FAILED');
            return $this->buildResult($importId, $fileName, 0, 0, 0, [], [], $headerResult['errors'], $startTime, []);
        }

        $headers = $headerResult['headers'];

        
        $totalRecords = $this->csvReader->countRows($filePath);
        $this->markProcessingStarted($importId, $totalRecords);

        $allErrors  = [];
        $seenEmails = [];
        $successful = 0;
        $failed     = 0;
        $batchCount = 0;

        $rowStatuses = [];

        $outputStatusLines = [];

        foreach ($this->csvReader->streamRows($filePath, $headers) as $row) {
            $rowNumber = (int) ($row['_row_number'] ?? 0);
            $result    = $this->processor->processRow($row, $rowNumber, $seenEmails);

            $email = strtolower(trim($row['email'] ?? ''));
            if ($email !== '') {
                $seenEmails[] = $email;
            }

            if ($result->isSuccess()) {
                $successful++;
            } else {
                $failed++;
                foreach ($result->errors as $error) {
                    $allErrors[] = $error;
                }
            }

            $statusString = $result->isSuccess() ? 'Success' : $this->buildFailStatus($result->errors);

            $rowStatuses[$rowNumber] = [
                'status' => $result->isSuccess() ? 'Success' : 'Fail',
                'errors' => $result->errors,
            ];

            $outputStatusLines[] = [
                'rowNumber' => $rowNumber,
                'row'       => $row,
                'status'    => $statusString,
            ];

            $batchCount++;
            if ($batchCount >= $this->config->getBatchSize()) {
                \Pimcore\Cache\RuntimeCache::clear();
                $batchCount = 0;
            }
        }

        
        $this->persistErrors($importId, $allErrors);
        $this->markCompleted($importId, $totalRecords, $successful, $failed);

        $resultCsvPath = $this->writeImportResultCsv($filePath, $headers, $outputStatusLines);

        $this->logger->info('CustomerImportService: result CSV written', [
            'import_id' => $importId,
            'path'      => $resultCsvPath,
        ]);

        $this->logger->info('CustomerImportService: import completed', [
            'import_id'  => $importId,
            'total'      => $totalRecords,
            'successful' => $successful,
            'failed'     => $failed,
        ]);

        $result = $this->buildResult($importId, $fileName, $totalRecords, $successful, $failed, $allErrors, [], [], $startTime, $rowStatuses);
        $result['result_csv_path'] = $resultCsvPath;
        return $result;
    }

    

    

    private function buildResult(
        string $importId,
        string $fileName,
        int    $total,
        int    $successful,
        int    $failed,
        array  $errors,
        array  $fileErrors,
        array  $headerErrors,
        float  $startTime,
        array  $rowStatuses = [],
    ): array {

        $durationSec = round(microtime(true) - $startTime, 2);
        $minutes     = intdiv((int) $durationSec, 60);
        $seconds     = fmod($durationSec, 60);

        if (!empty($fileErrors) || !empty($headerErrors)) {
            $status = 'HEADER_VALIDATION_FAILED';
        } elseif ($failed > 0 && $successful === 0) {
            $status = 'FAILED';
        } elseif ($failed > 0) {
            $status = 'COMPLETED_WITH_ERRORS';
        } else {
            $status = 'COMPLETED';
        }

        return [
            'import_id'          => $importId,
            'file_name'          => $fileName,
            'total_records'      => $total,
            'successful_records' => $successful,
            'failed_records'     => $failed,
            'errors'             => $errors,
            'file_errors'        => $fileErrors,
            'header_errors'      => $headerErrors,
            'status'             => $status,
            'row_statuses'      => $rowStatuses,
            'duration'           => $minutes > 0

                ? sprintf('%dm %ds', $minutes, (int) $seconds)
                : sprintf('%.2fs', $durationSec),
            'duration_seconds'   => $durationSec,
        ];
    }

    

    private function createAuditLog(
        string $importId,
        string $fileName,
        string $uploadedBy,
        int    $fileSize,
        string $fileHash = '',
    ): void {
        try {
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            $this->db->insert('customer_import_logs', [
                'import_id'          => $importId,
                'file_name'          => $fileName,
                'file_size_bytes'    => $fileSize,
                'file_hash'          => $fileHash,
                'uploaded_by'        => $uploadedBy,
                'upload_timestamp'   => $now,
                'processing_start'   => null,
                'processing_end'     => null,
                'status'             => 'PENDING',
                'total_records'      => 0,
                'successful_records' => 0,
                'failed_records'     => 0,
                'created_at'         => $now,
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('CustomerImportService: createAuditLog failed', ['error' => $e->getMessage()]);
        }
    }

    private function markProcessingStarted(string $importId, int $total): void
    {
        $this->updateLog($importId, [
            'status'           => 'PROCESSING',
            'processing_start' => (new \DateTime())->format('Y-m-d H:i:s'),
            'total_records'    => $total,
        ]);
    }

    private function markCompleted(string $importId, int $total, int $success, int $failed): void
    {
        $this->updateLog($importId, [
            'status'             => $failed > 0 ? 'COMPLETED_WITH_ERRORS' : 'COMPLETED',
            'processing_end'     => (new \DateTime())->format('Y-m-d H:i:s'),
            'total_records'      => $total,
            'successful_records' => $success,
            'failed_records'     => $failed,
        ]);
    }

    private function updateAuditStatus(string $importId, string $status): void
    {
        $this->updateLog($importId, [
            'status'         => $status,
            'processing_end' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

    private function updateLog(string $importId, array $data): void
    {
        try {
            $this->db->update('customer_import_logs', $data, ['import_id' => $importId]);
        } catch (\Throwable $e) {
            $this->logger->warning('CustomerImportService: updateLog failed', ['error' => $e->getMessage()]);
        }
    }

    

    
    private function persistErrors(string $importId, array $errors): void
    {
        if (empty($errors)) {
            return;
        }
        try {
            $this->db->beginTransaction();
            $now = (new \DateTime())->format('Y-m-d H:i:s');
            foreach ($errors as $error) {
                $this->db->insert('customer_import_errors', [
                    'import_id'     => $importId,
                    'row_num'       => $error->rowNumber,
                    'email'         => $error->sku,
                    'field_name'    => $error->fieldName,
                    'invalid_value' => mb_substr($error->invalidValue, 0, 500),
                    'error_code'    => $error->errorCode,
                    'error_message' => $error->errorMessage,
                    'created_at'    => $now,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            try { $this->db->rollBack(); } catch (\Throwable) {}
            $this->logger->error('CustomerImportService: persistErrors failed', ['error' => $e->getMessage()]);
        }
    }

    

    private function isDuplicateFile(string $fileHash): bool
    {
        try {
            
            
            
            $count = (int) $this->db->fetchOne(
                "SELECT COUNT(*) FROM customer_import_logs WHERE file_hash = ? AND status = 'COMPLETED'",
                [$fileHash]
            );
            return $count > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildNoChangeResult(string $fileName, float $startTime): array
    {
        $durationSec = round(microtime(true) - $startTime, 2);
        return [
            'import_id'          => '',
            'file_name'          => $fileName,
            'total_records'      => 0,
            'successful_records' => 0,
            'failed_records'     => 0,
            'errors'             => [],
            'file_errors'        => [],
            'header_errors'      => [],
            'status'             => 'NO_CHANGE',
            'duration'           => sprintf('%.2fs', $durationSec),
            'duration_seconds'   => $durationSec,
            'row_statuses'      => [],
        ];
    }

    

    private function generateImportId(): string
    {
        return 'cust_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
    private function buildFailStatus(array $errors): string
    {
        if (empty($errors)) {
            return 'Fail';
        }

        
        
        $first = $errors[0];
        return 'Fail - ' . ($first->errorMessage ?? 'Validation error');
    }

    private function writeImportResultCsv(
        string $inputFilePath,
        array $headers,
        array $outputStatusLines
    ): string {
        $dir = dirname($inputFilePath);
        $base = pathinfo($inputFilePath, PATHINFO_FILENAME);

        $outputFile = sprintf('%s_import_result_%s.csv', $base, date('Ymd_His'));
        $outputPath = $dir . DIRECTORY_SEPARATOR . $outputFile;

        $handle = fopen($outputPath, 'w');
        if ($handle === false) {
            $this->logger->warning('CustomerImportService: cannot create result CSV', [
                'path' => $outputPath,
            ]);
            return $outputPath;
        }

        
        $resultHeaders = array_map(static fn(string $h) => strtolower(trim($h)), $headers);
        $resultHeaders[] = self::OUTPUT_STATUS_COLUMN;
        fputcsv($handle, $resultHeaders);

        
        foreach ($outputStatusLines as $line) {
            $row = $line['row'] ?? [];

            
            $outRow = [];
            foreach ($headers as $h) {
                $outRow[] = $row[$h] ?? '';
            }

            $outRow[] = $line['status'] ?? 'Fail';
            fputcsv($handle, $outRow);
        }

        fclose($handle);

        return $outputPath;
    }
}

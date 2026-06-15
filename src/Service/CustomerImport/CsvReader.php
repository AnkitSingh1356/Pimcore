<?php

namespace App\Service\CustomerImport;

use Psr\Log\LoggerInterface;

class CsvReader
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    

    public function validateFile(string $filePath): array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            $errors[] = sprintf('File not found: %s', $filePath);
            return $errors;
        }

        if (!is_readable($filePath)) {
            $errors[] = sprintf('File is not readable: %s', $filePath);
            return $errors;
        }

        if (filesize($filePath) === 0) {
            $errors[] = 'The CSV file is empty.';
            return $errors;
        }

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $errors[] = sprintf('Invalid file extension ".%s" — only .csv files are accepted.', $ext);
        }

        return $errors;
    }

    

    

    public function validateHeaders(string $filePath, array $requiredHeaders): array
    {
        $handle = $this->openFile($filePath);
        if ($handle === false) {
            return ['headers' => [], 'errors' => ['Could not open file for reading.']];
        }

        $rawHeaders = fgetcsv($handle);
        fclose($handle);

        if ($rawHeaders === false || $rawHeaders === null) {
            return ['headers' => [], 'errors' => ['CSV file appears to be empty or malformed.']];
        }

        $headers = array_map(fn(string $h) => strtolower(trim($h)), $rawHeaders);
        $errors  = [];

        foreach ($headers as $idx => $h) {
            if ($h === '') {
                $errors[] = sprintf('Column %d has an empty header name.', $idx + 1);
            }
        }

        $counts = array_count_values($headers);
        foreach ($counts as $header => $count) {
            if ($count > 1 && $header !== '') {
                $errors[] = sprintf('Duplicate column "%s" found (%d times).', $header, $count);
            }
        }

        $missing = array_diff($requiredHeaders, $headers);
        foreach ($missing as $m) {
            $errors[] = sprintf('Required column "%s" is missing from the file.', $m);
        }

        return ['headers' => $headers, 'errors' => $errors];
    }

    

    public function streamRows(string $filePath, array $headers): \Generator
    {
        $handle = $this->openFile($filePath);
        if ($handle === false) {
            $this->logger->error('CsvReader: failed to open file for streaming', ['path' => $filePath]);
            return;
        }

        fgetcsv($handle); 

        $rowNumber = 1; 
        while (($rawRow = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isBlankRow($rawRow)) {
                continue;
            }

            $row = ['_row_number' => $rowNumber];
            foreach ($headers as $idx => $header) {
                $row[$header] = isset($rawRow[$idx]) ? trim($rawRow[$idx]) : '';
            }

            yield $row;
        }

        fclose($handle);
    }

    public function countRows(string $filePath): int
    {
        $handle = $this->openFile($filePath);
        if ($handle === false) {
            return 0;
        }

        fgetcsv($handle); 

        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (!$this->isBlankRow($row)) {
                $count++;
            }
        }

        fclose($handle);
        return $count;
    }

    
    private function openFile(string $filePath)
    {
        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            $this->logger->error('CsvReader: cannot open file', ['path' => $filePath]);
            return false;
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        return $handle;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }
        return true;
    }
}

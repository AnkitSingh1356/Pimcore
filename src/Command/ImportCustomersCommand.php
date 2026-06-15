<?php

namespace App\Command;

use App\Installer\CustomerImportInstaller;
use App\Service\CustomerImportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-customers',
    description: 'Import customers from a CSV file into Pimcore',
)]
class ImportCustomersCommand extends Command
{
    public function __construct(
        private readonly CustomerImportService   $importService,
        private readonly CustomerImportInstaller $installer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Path to the customer CSV file (absolute or relative to CWD)'
            )
            ->addOption(
                'imported-by',
                null,
                InputOption::VALUE_OPTIONAL,
                'Username recorded in the audit trail',
                'cli'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $filePath   = $input->getArgument('file');
        $importedBy = (string) $input->getOption('imported-by');

        if (!str_starts_with($filePath, '/')) {
            $filePath = getcwd() . DIRECTORY_SEPARATOR . $filePath;
        }

        $io->title('Customer CSV Import');
        $io->listing([
            'File      : ' . $filePath,
            'Import by : ' . $importedBy,
        ]);

        if (!$this->installer->isInstalled()) {
            $io->text('<info>Running first-time installer…</info>');
            $this->installer->install();
            $io->newLine();
        } else {
            $this->installer->renameRowNumberColumn();
        }

        $io->text('Processing records…');

        $result = $this->importService->importFromFile($filePath, $importedBy);

        $io->newLine();

        if (($result['status'] ?? '') === 'NO_CHANGE') {
            $io->success('No data changed — this file has already been imported successfully.');
            return Command::SUCCESS;
        }

        if (!empty($result['file_errors'])) {
            $io->error('File validation failed:');
            foreach ($result['file_errors'] as $err) {
                $io->writeln('  • ' . $err);
            }
            return Command::FAILURE;
        }

        if (!empty($result['header_errors'])) {
            $io->error('CSV header validation failed:');
            foreach ($result['header_errors'] as $err) {
                $io->writeln('  • ' . $err);
            }
            return Command::FAILURE;
        }

        $io->section('Import Summary');
        $io->writeln(sprintf(
            'Total Records Processed : <info>%s</info>',
            number_format($result['total_records'])
        ));
        $io->writeln(sprintf(
            'Successfully Imported   : <info>%s</info>',
            number_format($result['successful_records'])
        ));
        $io->writeln(sprintf(
            'Failed Records          : %s%s%s',
            $result['failed_records'] > 0 ? '<error>' : '',
            number_format($result['failed_records']),
            $result['failed_records'] > 0 ? '</error>' : ''
        ));
        $io->writeln('Duration                : ' . $result['duration']);
        $io->writeln('Import ID               : ' . $result['import_id']);
        $io->newLine();

        $rowStatuses = $result['row_statuses'] ?? [];
        if (!empty($rowStatuses)) {
            $io->section('Row Status');

            ksort($rowStatuses);
            foreach ($rowStatuses as $rowNumber => $info) {
                $status = $info['status'] ?? 'Fail';
                $io->writeln(sprintf('Row %-3d => <info>%s</info>', (int) $rowNumber, $status === 'Success' ? 'Success' : 'Fail'));

                if (($info['status'] ?? '') === 'Fail' && !empty($info['errors'])) {
                    foreach ($info['errors'] as $error) {
                        $io->writeln(sprintf(
                            '  • %-20s | %-30s | %s',
                            mb_strimwidth($error->fieldName, 0, 20),
                            mb_strimwidth($error->invalidValue !== '' ? $error->invalidValue : 'NULL', 0, 30, '…'),
                            $error->errorMessage
                        ));
                    }
                }
            }
            $io->newLine();
        }

        if (!empty($result['result_csv_path'])) {
            $io->writeln('Result CSV              : <info>' . $result['result_csv_path'] . '</info>');
            $io->newLine();
        }

        if ($result['successful_records'] === 0 && $result['total_records'] > 0) {
            $io->error(sprintf(
                'Import failed — 0 of %d record(s) were imported.',
                $result['total_records']
            ));
            return Command::FAILURE;
        }

        if ($result['failed_records'] > 0) {
            $io->warning(sprintf(
                'Import completed with errors — %d imported, %d skipped.',
                $result['successful_records'],
                $result['failed_records']
            ));
            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'All %d customer record(s) imported successfully.',
            $result['successful_records']
        ));

        return Command::SUCCESS;
    }
}

<?php

namespace App\Command;

use Carbon\Carbon;
use Pimcore\Console\AbstractCommand;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\ReleaseNote;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:setup-release-notes',
    description: 'Bootstraps the ReleaseNote Pimcore class (creates DB tables) and optionally seeds sample release notes'
)]
class SetupReleaseNotesCommand extends AbstractCommand
{
    public function __construct(private readonly KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('skip-seed', null, InputOption::VALUE_NONE, 'Skip seeding sample release notes')
            ->addOption('force-reseed', null, InputOption::VALUE_NONE, 'Delete existing release notes and re-seed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->setupClass($output);
        $this->ensureFolder($output);

        $skipSeed    = $input->getOption('skip-seed');
        $forceReseed = $input->getOption('force-reseed');

        if (!$skipSeed) {
            $this->seedData($output, (bool) $forceReseed);
        }

        $output->writeln('<info>Release Notes setup complete.</info>');
        $output->writeln('');
        $output->writeln('Next steps:');
        $output->writeln('  • Manage content via Pimcore Admin → Data Objects → /release-notes');
        $output->writeln('  • API: GET /api/release-notes');
        $output->writeln('  • API: GET /api/release-notes/latest');
        $output->writeln('  • API: GET /api/release-notes/{version}');

        return self::SUCCESS;
    }

    private function setupClass(OutputInterface $output): void
    {
        $output->writeln('<comment>Setting up ReleaseNote class...</comment>');

        $existing = ClassDefinition::getByName('ReleaseNote');
        if ($existing) {
            $output->writeln('  <info>✓ ReleaseNote class already registered – rebuilding tables...</info>');
            try {
                $existing->save();
                $output->writeln('  <info>✓ Database tables updated.</info>');
            } catch (\Throwable $e) {
                $output->writeln("  <error>Warning: {$e->getMessage()}</error>");
            }
            return;
        }

        $defFile = $this->kernel->getProjectDir() . '/var/classes/definition_ReleaseNote.php';
        if (!file_exists($defFile)) {
            $output->writeln("  <error>Definition file not found: {$defFile}</error>");
            $output->writeln('  Please ensure var/classes/definition_ReleaseNote.php exists.');
            return;
        }

        try {
            /** @var ClassDefinition $def */
            $def = include $defFile;
            $def->save();
            $output->writeln('  <info>✓ ReleaseNote class created and database tables provisioned.</info>');
        } catch (\Throwable $e) {
            $output->writeln("  <error>Failed to create class: {$e->getMessage()}</error>");
        }
    }

    private function ensureFolder(OutputInterface $output): void
    {
        $output->writeln('<comment>Ensuring /release-notes folder exists...</comment>');

        $path = '/release-notes';
        $folder = DataObject::getByPath($path);

        if ($folder instanceof DataObject\Folder) {
            $output->writeln('  <info>✓ Folder already exists.</info>');
            return;
        }

        try {
            $folder = DataObject\Folder::create([
                'key'        => 'release-notes',
                'parentId'   => 1,
                'published'  => true,
            ]);
            $output->writeln('  <info>✓ Folder created at /release-notes.</info>');
        } catch (\Throwable $e) {
            $output->writeln("  <error>Failed to create folder: {$e->getMessage()}</error>");
        }
    }

    private function seedData(OutputInterface $output, bool $forceReseed = false): void
    {
        $output->writeln('<comment>Seeding sample release notes...</comment>');

        $folder = DataObject::getByPath('/release-notes');
        if (!$folder instanceof DataObject\Folder) {
            $output->writeln('  <error>Folder /release-notes not found – skipping seed.</error>');
            return;
        }

        if ($forceReseed) {
            $listing = new ReleaseNote\Listing();
            $listing->setCondition('1=1');
            foreach ($listing->load() as $note) {
                $note->delete();
            }
            $output->writeln('  Deleted existing release notes.');
        }

        $existing = new ReleaseNote\Listing();
        $existing->setCondition('1=1');
        if (!empty($existing->load()) && !$forceReseed) {
            $output->writeln('  <info>✓ Release notes already seeded. Use --force-reseed to overwrite.</info>');
            return;
        }

        foreach ($this->sampleReleases() as $data) {
            try {
                $note = new ReleaseNote();
                $note->setParent($folder);
                $note->setKey(\Pimcore\Model\Element\Service::getValidKey($data['version'], 'object'));
                $note->setPublished(true);
                $note->setTitle($data['title']);
                $note->setVersion($data['version']);
                $note->setReleaseDate(Carbon::parse($data['releaseDate']));
                $note->setIsLatest($data['isLatest'] ?? false);
                $note->setStatus('published');
                $note->setSummary($data['summary']);
                $note->setTags($data['tags']);
                $note->setAffectedModules($data['affectedModules'] ?? '');
                $note->setAuthor($data['author'] ?? 'Admin');
                $note->setSortOrder($data['sortOrder'] ?? 100);
                $note->setSections(json_encode($data['sections'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                $note->save();
                $output->writeln("  <info>✓ Created: v{$data['version']} – {$data['title']}</info>");
            } catch (\Throwable $e) {
                $output->writeln("  <error>Failed to seed v{$data['version']}: {$e->getMessage()}</error>");
            }
        }
    }

    private function sampleReleases(): array
    {
        return [
            [
                'title'           => 'Dunlop Brand System Rollout',
                'version'         => '2.10.1',
                'releaseDate'     => '2026-05-22',
                'isLatest'        => true,
                'summary'         => 'Platform-wide brand alignment to official Dunlop guidelines — color tokens, typography, and navigation refresh.',
                'tags'            => 'BRANDING,DESIGN SYSTEM,UX',
                'affectedModules' => 'customer360,dealer-network,segments,behavioral-analytics',
                'author'          => 'CDP Platform Team',
                'sortOrder'       => 1,
                'sections'        => [
                    [
                        'sectionType' => 'Highlights',
                        'sectionName' => 'Highlights',
                        'items'       => [
                            'Applied rgb(14, 165, 233) and brand black across the platform',
                            'Unified sidebar, login, and primary actions with the official brand palette',
                            'Aligned typography with the Inter / Arial / Proxima Nova brand stack',
                        ],
                        'order'       => 1,
                    ],
                    [
                        'sectionType' => 'Enhancements',
                        'sectionName' => 'Enhancements',
                        'items'       => [
                            'Replaced legacy navy/blue accents with brand-correct color tokens',
                            'Updated chart accent palette to match brand guidelines',
                            'Improved contrast on active navigation states',
                        ],
                        'order'       => 2,
                    ],
                ],
            ],
            [
                'title'           => 'Advanced Segmentation & Dealer Network Visibility',
                'version'         => '2.9.0',
                'releaseDate'     => '2026-04-29',
                'isLatest'        => false,
                'summary'         => 'Activity Window filters, expanded segment targeting, and a new Dealer Network view with territory mapping.',
                'tags'            => 'SEGMENTATION,DEALER NETWORK,FILTERING,UX',
                'affectedModules' => 'segments,dealer-network',
                'author'          => 'CDP Platform Team',
                'sortOrder'       => 2,
                'sections'        => [
                    [
                        'sectionType' => 'Highlights',
                        'sectionName' => 'Highlights',
                        'items'       => [
                            'Dynamic Activity Window filter with 8 presets (7d → 12m, YTD)',
                            'Expanded attribute-based targeting in the Segment Builder',
                            'Dealer Network view with sample reps and territory relationships',
                        ],
                        'order'       => 1,
                    ],
                    [
                        'sectionType' => 'Features',
                        'sectionName' => 'Features',
                        'items'       => [
                            'Activity Window dropdown with presets: 7d, 14d, 30d, 60d, 90d, 6m, 12m, YTD',
                            'Dealer Network page surfacing rep coverage and dealer relationships',
                            'Sample reps dataset to demonstrate territory and account hierarchies',
                        ],
                        'order'       => 2,
                    ],
                    [
                        'sectionType' => 'Enhancements',
                        'sectionName' => 'Enhancements',
                        'items'       => [
                            'Brand-color highlighting for the active Activity Window selection',
                            'Segment Builder dynamically injects last_activity rule from selected window',
                            'Cleaner, more intuitive segmentation controls aligned with the design system',
                        ],
                        'order'       => 3,
                    ],
                    [
                        'sectionType' => 'Data & Platform',
                        'sectionName' => 'Data & Platform',
                        'items'       => [
                            'Sample reps structure scaffolds future CRM/CDP enrichment',
                            'Segmentation rules extended to support relative date offsets and YTD',
                        ],
                        'order'       => 4,
                    ],
                    [
                        'sectionType' => 'Fixes',
                        'sectionName' => 'Fixes',
                        'items'       => [
                            'Resolved inconsistent date-range handling across segment previews',
                        ],
                        'order'       => 5,
                    ],
                ],
            ],
            [
                'title'           => 'Data Governance & Audit Framework',
                'version'         => '2.8.0',
                'releaseDate'     => '2026-04-23',
                'isLatest'        => false,
                'summary'         => 'End-to-end audit trail for bulk uploads, per-attribute source tracking, and configurable duplicate resolution.',
                'tags'            => 'AUDIT,DATA QUALITY,GOVERNANCE,BULK UPLOAD',
                'affectedModules' => 'data-model,customer360',
                'author'          => 'CDP Platform Team',
                'sortOrder'       => 3,
                'sections'        => [
                    [
                        'sectionType' => 'Highlights',
                        'sectionName' => 'Highlights',
                        'items'       => [
                            'End-to-end audit trail for every bulk upload and record update',
                            'Source-of-origin tracking on every ingested attribute',
                            'Configurable duplicate management: skip, overwrite, or review',
                        ],
                        'order'       => 1,
                    ],
                    [
                        'sectionType' => 'Features',
                        'sectionName' => 'Features',
                        'items'       => [
                            'Upload audit log capturing file, user, timestamp, and outcome per job',
                            "Per-attribute source labels (e.g., 'Launch Squad Upload', 'CRM Sync')",
                            'Duplicate resolution workflow with skip / overwrite / review actions',
                        ],
                        'order'       => 2,
                    ],
                    [
                        'sectionType' => 'Governance Updates',
                        'sectionName' => 'Governance Updates',
                        'items'       => [
                            'All bulk operations recorded in the central audit trail',
                            'Data quality validation framework wired into ingestion pipeline',
                            'Steward-visible upload history per entity type',
                        ],
                        'order'       => 3,
                    ],
                    [
                        'sectionType' => 'Data & Platform',
                        'sectionName' => 'Data & Platform',
                        'items'       => [
                            'upload_jobs and upload_errors tables for full ingestion lineage',
                            'Source field standardized across customer, dealer, and persona attributes',
                        ],
                        'order'       => 4,
                    ],
                    [
                        'sectionType' => 'Fixes',
                        'sectionName' => 'Fixes',
                        'items'       => [
                            'Improved error messaging on malformed CSV headers',
                            'Resolved race condition when multiple uploads ran concurrently',
                        ],
                        'order'       => 5,
                    ],
                ],
            ],
            [
                'title'           => 'Profile 360 & Segmentation Foundation',
                'version'         => '2.7.0',
                'releaseDate'     => '2026-04-22',
                'isLatest'        => false,
                'summary'         => 'Unified 360-degree customer profile, segment builder with rule logic, and persona tagging infrastructure.',
                'tags'            => 'PROFILE 360,SEGMENTATION,TAGGING,PERSONAS',
                'affectedModules' => 'customer360,segments',
                'author'          => 'CDP Platform Team',
                'sortOrder'       => 4,
                'sections'        => [
                    [
                        'sectionType' => 'Highlights',
                        'sectionName' => 'Highlights',
                        'items'       => [
                            'Unified 360-degree profile view across customers, dealers, and personas',
                            'Segment Builder with AND/OR rule logic and attribute targeting',
                            'Persona tagging system for multi-profile enrichment',
                        ],
                        'order'       => 1,
                    ],
                    [
                        'sectionType' => 'Features',
                        'sectionName' => 'Features',
                        'items'       => [
                            'Customer 360 modal with demographics, behavior, and consent tabs',
                            'Segment preview showing estimated match count before save',
                            'Persona assignment across B2C, B2B, and Dealer profiles',
                        ],
                        'order'       => 2,
                    ],
                    [
                        'sectionType' => 'Enhancements',
                        'sectionName' => 'Enhancements',
                        'items'       => [
                            'Segment modal now supports nested rule groups',
                            'Customer profile header surfaces identity resolution status',
                        ],
                        'order'       => 3,
                    ],
                    [
                        'sectionType' => 'Data & Platform',
                        'sectionName' => 'Data & Platform',
                        'items'       => [
                            'segments and segment_rules tables with full rule schema',
                            'persona_attributes table for multi-persona enrichment',
                        ],
                        'order'       => 4,
                    ],
                ],
            ],
            [
                'title'           => 'RBAC & Access Control Layer',
                'version'         => '2.6.0',
                'releaseDate'     => '2026-04-15',
                'isLatest'        => false,
                'summary'         => 'Role-based access control with granular permissions, menu visibility control, and a full audit trail.',
                'tags'            => 'RBAC,SECURITY,ACCESS MANAGEMENT,AUDIT',
                'affectedModules' => 'access-management',
                'author'          => 'CDP Platform Team',
                'sortOrder'       => 5,
                'sections'        => [
                    [
                        'sectionType' => 'Highlights',
                        'sectionName' => 'Highlights',
                        'items'       => [
                            'Full role-based access control with module-level permissions',
                            'Menu and page visibility driven by assigned roles',
                            'Audit trail capturing every permission change with user context',
                        ],
                        'order'       => 1,
                    ],
                    [
                        'sectionType' => 'Features',
                        'sectionName' => 'Features',
                        'items'       => [
                            'Users, Roles, Modules, Permissions management UI',
                            'Granular action-level permissions: view, create, edit, delete, export',
                            'User Access Summary showing effective permissions per user',
                        ],
                        'order'       => 2,
                    ],
                    [
                        'sectionType' => 'Governance Updates',
                        'sectionName' => 'Governance Updates',
                        'items'       => [
                            'All role and permission changes recorded in audit trail',
                            'Admin-only override for unrestricted access across all modules',
                        ],
                        'order'       => 3,
                    ],
                    [
                        'sectionType' => 'Data & Platform',
                        'sectionName' => 'Data & Platform',
                        'items'       => [
                            'users, roles, permissions, menus, pages normalized schema',
                            'JWT authentication with bearer token pattern',
                        ],
                        'order'       => 4,
                    ],
                    [
                        'sectionType' => 'Fixes',
                        'sectionName' => 'Fixes',
                        'items'       => [
                            'Fixed token refresh race condition on session expiry',
                        ],
                        'order'       => 5,
                    ],
                ],
            ],
        ];
    }
}

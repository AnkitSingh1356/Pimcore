<?php

namespace App\Controller;

use Pimcore\Model\DataObject\ReleaseNote;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ReleaseNoteController extends AbstractController
{
    private const CORS_HEADERS = [
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        'Access-Control-Max-Age'       => '3600',
    ];

    private function corsJson(mixed $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        foreach (self::CORS_HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }
        return $response;
    }

    private function corsOptions(): Response
    {
        $response = new Response('', 204);
        foreach (self::CORS_HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }
        return $response;
    }

    #[Route('/api/release-notes', name: 'api_release_notes_list', methods: ['GET', 'OPTIONS'])]
    public function list(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->corsOptions();
        }

        try {
            $page   = max(1, (int) $request->query->get('page', 1));
            $limit  = min(50, max(1, (int) $request->query->get('limit', 10)));
            $search = trim((string) $request->query->get('search', ''));
            $tag    = trim((string) $request->query->get('tag', ''));
            $ver    = trim((string) $request->query->get('version', ''));
            $sort   = strtoupper((string) $request->query->get('sort', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

            $listing = new ReleaseNote\Listing();
            $listing->setUnpublished(false);

            $conditions = ["`status` = ?"];
            $params     = ['published'];

            if ($search !== '') {
                $conditions[] = "(`title` LIKE ? OR `summary` LIKE ? OR `version` LIKE ?)";
                $params[]     = "%{$search}%";
                $params[]     = "%{$search}%";
                $params[]     = "%{$search}%";
            }

            if ($tag !== '') {
                $conditions[] = "`tags` LIKE ?";
                $params[]     = "%{$tag}%";
            }

            if ($ver !== '') {
                $conditions[] = "`version` = ?";
                $params[]     = $ver;
            }

            $listing->setCondition(implode(' AND ', $conditions), $params);
            $listing->setOrderKey('releaseDate');
            $listing->setOrder($sort);
            $listing->setLimit($limit);
            $listing->setOffset(($page - 1) * $limit);

            $total = (int) $listing->getTotalCount();
            $notes = $listing->load();

            return $this->corsJson([
                'data'       => array_map($this->serializeNote(...), $notes),
                'pagination' => [
                    'page'  => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => max(1, (int) ceil($total / $limit)),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->corsJson([
                'data'       => [],
                'pagination' => ['page' => 1, 'limit' => 10, 'total' => 0, 'pages' => 0],
                'error'      => $this->isSafeError($e) ? $e->getMessage() : 'Failed to load release notes',
            ], 500);
        }
    }

    #[Route('/api/release-notes/latest', name: 'api_release_notes_latest', methods: ['GET', 'OPTIONS'], priority: 10)]
    public function latest(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->corsOptions();
        }

        try {
            $listing = new ReleaseNote\Listing();
            $listing->setCondition('`isLatest` = ? AND `status` = ?', [1, 'published']);
            $listing->setLimit(1);
            $notes = $listing->load();

            if (empty($notes)) {
                $listing2 = new ReleaseNote\Listing();
                $listing2->setCondition('`status` = ?', ['published']);
                $listing2->setOrderKey('releaseDate');
                $listing2->setOrder('DESC');
                $listing2->setLimit(1);
                $notes = $listing2->load();
            }

            if (empty($notes)) {
                return $this->corsJson(['error' => 'No published release notes found'], 404);
            }

            return $this->corsJson($this->serializeNote($notes[0]));
        } catch (\Throwable $e) {
            return $this->corsJson(['error' => 'Failed to load latest release note'], 500);
        }
    }

    #[Route('/api/release-notes/{version}', name: 'api_release_notes_by_version', methods: ['GET', 'OPTIONS'])]
    public function byVersion(string $version, Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->corsOptions();
        }

        try {
            $listing = new ReleaseNote\Listing();
            $listing->setCondition('`version` = ? AND `status` = ?', [$version, 'published']);
            $listing->setLimit(1);
            $notes = $listing->load();

            if (empty($notes)) {
                return $this->corsJson(['error' => 'Release note not found for version: ' . $version], 404);
            }

            return $this->corsJson($this->serializeNote($notes[0]));
        } catch (\Throwable $e) {
            return $this->corsJson(['error' => 'Failed to load release note'], 500);
        }
    }

    private function serializeNote(ReleaseNote $note): array
    {
        $releaseDate = $note->getReleaseDate();
        $sections    = $this->parseSections($note->getSections());
        $tags        = $this->parseCommaSeparated($note->getTags());
        $modules     = $this->parseCommaSeparated($note->getAffectedModules());

        return [
            'id'                   => $note->getId(),
            'title'                => $note->getTitle(),
            'version'              => $note->getVersion(),
            'releaseDate'          => $releaseDate?->format('Y-m-d'),
            'releaseDateFormatted' => $releaseDate?->format('F j, Y'),
            'isLatest'             => (bool) $note->getIsLatest(),
            'status'               => $note->getStatus(),
            'summary'              => $note->getSummary(),
            'tags'                 => $tags,
            'affectedModules'      => $modules,
            'author'               => $note->getAuthor(),
            'sortOrder'            => (int) ($note->getSortOrder() ?? 0),
            'sections'             => $sections,
        ];
    }

    private function parseSections(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 10, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }
            usort($decoded, static fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
            return $decoded;
        } catch (\JsonException) {
            return [];
        }
    }

    private function parseCommaSeparated(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        return array_values(
            array_filter(
                array_map('trim', explode(',', $raw))
            )
        );
    }

    private function isSafeError(\Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'not found') ||
               str_contains($e->getMessage(), 'does not exist');
    }
}

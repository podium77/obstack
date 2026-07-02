<?php

declare(strict_types=1);

namespace App\Controller\Admin\API;

use App\Service\DataImportService;
use App\Service\DataExportService;
use App\Service\BulkOperationService;
use App\Service\DataValidationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class DataManagementController extends AbstractController
{
    public function __construct(
        private DataImportService $importService,
        private DataExportService $exportService,
        private BulkOperationService $bulkService,
        private DataValidationService $validationService,
    ) {}

    /**
     * Import data from CSV file
     */
    #[Route('/import/csv', name: 'import_csv', methods: ['POST'])]
    public function importCsv(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['content']) || !isset($data['tableName'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: content, tableName',
                ], Response::HTTP_BAD_REQUEST);
            }

            $tableName = $data['tableName'];
            $csvContent = $data['content'];
            $options = $data['options'] ?? [];

            // Validate first
            $validation = $this->validationService->validateCsvData(
                $csvContent,
                $this->getDoctrine()->getConnection(),
                $tableName,
                $options
            );

            if (!$validation['valid']) {
                return $this->json([
                    'success' => false,
                    'error' => 'CSV validation failed',
                    'validation' => $validation,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            // Import
            $result = $this->importService->importCsv(
                null, // In production, would use real DatabaseConnection
                $csvContent,
                $tableName,
                $options
            );

            return $this->json([
                'success' => $result['success'] ?? false,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import data from JSON file
     */
    #[Route('/import/json', name: 'import_json', methods: ['POST'])]
    public function importJson(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['content']) || !isset($data['tableName'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: content, tableName',
                ], Response::HTTP_BAD_REQUEST);
            }

            $tableName = $data['tableName'];
            $jsonContent = $data['content'];
            $options = $data['options'] ?? [];

            $result = $this->importService->importJson(
                null,
                $jsonContent,
                $tableName,
                $options
            );

            return $this->json([
                'success' => $result['success'] ?? false,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate data before import
     */
    #[Route('/import/validate', name: 'import_validate', methods: ['POST'])]
    public function validateImport(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['content']) || !isset($data['tableName'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: content, tableName',
                ], Response::HTTP_BAD_REQUEST);
            }

            $tableName = $data['tableName'];
            $content = $data['content'];
            $format = $data['format'] ?? 'csv';
            $options = $data['options'] ?? [];

            $validation = $this->validationService->validateCsvData(
                $content,
                $this->getDoctrine()->getConnection(),
                $tableName,
                $options
            );

            return $this->json([
                'success' => true,
                'data' => $validation,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export data to CSV
     */
    #[Route('/export/csv', name: 'export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): Response
    {
        try {
            $tableName = $request->query->getString('table');
            $limit = $request->query->getInt('limit', 10000);
            $offset = $request->query->getInt('offset', 0);

            if (!$tableName) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Table name required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $options = [
                'limit' => $limit,
                'offset' => $offset,
            ];

            $csvContent = $this->exportService->exportToCsv(
                null,
                $tableName,
                $options
            );

            $response = new Response($csvContent);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$tableName}.csv\"");

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export data to JSON
     */
    #[Route('/export/json', name: 'export_json', methods: ['GET'])]
    public function exportJson(Request $request): Response
    {
        try {
            $tableName = $request->query->getString('table');
            $limit = $request->query->getInt('limit', 10000);
            $offset = $request->query->getInt('offset', 0);

            if (!$tableName) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Table name required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $options = [
                'limit' => $limit,
                'offset' => $offset,
                'pretty' => true,
            ];

            $jsonContent = $this->exportService->exportToJson(
                null,
                $tableName,
                $options
            );

            $response = new Response($jsonContent);
            $response->headers->set('Content-Type', 'application/json');
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$tableName}.json\"");

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export data to JSONL
     */
    #[Route('/export/jsonl', name: 'export_jsonl', methods: ['GET'])]
    public function exportJsonL(Request $request): Response
    {
        try {
            $tableName = $request->query->getString('table');
            $limit = $request->query->getInt('limit', 10000);
            $offset = $request->query->getInt('offset', 0);

            if (!$tableName) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Table name required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $options = [
                'limit' => $limit,
                'offset' => $offset,
            ];

            $jsonlContent = $this->exportService->exportToJsonL(
                null,
                $tableName,
                $options
            );

            $response = new Response($jsonlContent);
            $response->headers->set('Content-Type', 'application/jsonl');
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$tableName}.jsonl\"");

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export data as Excel
     */
    #[Route('/export/excel', name: 'export_excel', methods: ['GET'])]
    public function exportExcel(Request $request): Response
    {
        try {
            $tableName = $request->query->getString('table');
            $limit = $request->query->getInt('limit', 10000);
            $offset = $request->query->getInt('offset', 0);

            if (!$tableName) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Table name required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $options = [
                'limit' => $limit,
                'offset' => $offset,
                'delimiter' => ';',
            ];

            $content = $this->exportService->exportToExcel(
                null,
                $tableName,
                $options
            );

            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
            $response->headers->set('Content-Disposition', "attachment; filename=\"{$tableName}.csv\"");

            return $response;
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get table structure
     */
    #[Route('/export/structure', name: 'export_structure', methods: ['GET'])]
    public function getTableStructure(Request $request): JsonResponse
    {
        try {
            $tableName = $request->query->getString('table');

            if (!$tableName) {
                return $this->json([
                    'success' => false,
                    'error' => 'Table name required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $structure = $this->exportService->exportTableStructure(
                null,
                $tableName
            );

            return $this->json([
                'success' => true,
                'data' => $structure,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get table statistics
     */
    #[Route('/export/stats', name: 'export_stats', methods: ['GET'])]
    public function getTableStats(Request $request): JsonResponse
    {
        try {
            $tableName = $request->query->getString('table');

            if (!$tableName) {
                return $this->json([
                    'success' => false,
                    'error' => 'Table name required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $stats = $this->exportService->getTableStats(null, $tableName);

            return $this->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk insert
     */
    #[Route('/bulk/insert', name: 'bulk_insert', methods: ['POST'])]
    public function bulkInsert(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['tableName']) || !isset($data['rows'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: tableName, rows',
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->bulkService->bulkInsert(
                null,
                $data['tableName'],
                $data['rows'],
                $data['batchSize'] ?? 1000
            );

            return $this->json([
                'success' => $result['success'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk update
     */
    #[Route('/bulk/update', name: 'bulk_update', methods: ['POST'])]
    public function bulkUpdate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['tableName']) || !isset($data['updateData']) || !isset($data['conditions'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: tableName, updateData, conditions',
                ], Response::HTTP_BAD_REQUEST);
            }

            $result = $this->bulkService->bulkUpdate(
                null,
                $data['tableName'],
                $data['updateData'],
                $data['conditions']
            );

            return $this->json([
                'success' => $result['success'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk delete
     */
    #[Route('/bulk/delete', name: 'bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['tableName']) || !isset($data['conditions'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: tableName, conditions',
                ], Response::HTTP_BAD_REQUEST);
            }

            $confirm = $data['confirm'] ?? false;

            $result = $this->bulkService->bulkDelete(
                null,
                $data['tableName'],
                $data['conditions'],
                (bool)$confirm
            );

            return $this->json([
                'success' => $result['success'],
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Analyze data quality
     */
    #[Route('/data/quality', name: 'data_quality', methods: ['GET'])]
    public function analyzeQuality(Request $request): JsonResponse
    {
        try {
            $tableName = $request->query->getString('table');

            if (!$tableName) {
                return $this->json([
                    'success' => false,
                    'error' => 'Table name required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $quality = $this->validationService->analyzeDataQuality(
                $this->getDoctrine()->getConnection(),
                $tableName
            );

            return $this->json([
                'success' => true,
                'data' => $quality,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

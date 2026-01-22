<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use App\Service\TenantManager;

/**
 * Controller for viewing application error logs
 */
#[Route("/{dominio}/api/errors", name: "api_errors_")]
class ErrorLogController extends AbstractController
{
    private LoggerInterface $logger;
    private string $logDir;
    private TenantManager $tenantManager;

    public function __construct(LoggerInterface $logger, string $kernelProjectDir, TenantManager $tenantManager)
    {
        $this->logger = $logger;
        $this->logDir = $kernelProjectDir . '/var/log';
        $this->tenantManager = $tenantManager;
    }

    /**
     * Get a list of available log files
     */
    #[Route("/files", name: "files", methods: ["GET"])]
    public function getLogFiles(string $dominio): JsonResponse
    {

        try {
            $filesystem = new Filesystem();

            // Check for tenant-specific log directory
            $tenantLogDir = $this->logDir . '/' . $dominio;
            $logDir = $filesystem->exists($tenantLogDir) ? $tenantLogDir : $this->logDir;

            if (!$filesystem->exists($logDir)) {
                return $this->json([
                    'error' => 'Log directory not found'
                ], 404);
            }

            $finder = new Finder();
            $finder->files()->in($logDir)->name('*.log')->sortByModifiedTime();

            $files = [];
            foreach ($finder as $file) {
                $files[] = [
                    'name' => $file->getFilename(),
                    'path' => $file->getRelativePathname(),
                    'size' => $file->getSize(),
                    'modified' => $file->getMTime()
                ];
            }

            return $this->json([
                'files' => $files
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving log files', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Error retrieving log files: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the content of a specific log file
     */
    #[Route("/file/{filename}", name: "file_content", methods: ["GET"])]
    public function getLogFileContent(string $dominio, string $filename, Request $request): JsonResponse
    {

        try {
            $filesystem = new Filesystem();

            // Check for tenant-specific log directory
            $tenantLogDir = $this->logDir . '/' . $dominio;
            $tenantFilePath = $tenantLogDir . '/' . $filename;
            $mainFilePath = $this->logDir . '/' . $filename;

            // Use tenant-specific file if it exists, otherwise use main log file
            $filePath = $filesystem->exists($tenantFilePath) ? $tenantFilePath : $mainFilePath;

            if (!$filesystem->exists($filePath)) {
                return $this->json([
                    'error' => 'Log file not found'
                ], 404);
            }

            // Get query parameters for filtering
            $level = $request->query->get('level');
            $limit = $request->query->getInt('limit', 100);
            $offset = $request->query->getInt('offset', 0);
            $search = $request->query->get('search');

            // Read the file content
            $content = file_get_contents($filePath);
            $lines = explode("\n", $content);

            // Filter lines
            $filteredLines = [];
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                // Filter by log level if specified
                if ($level && !$this->lineContainsLevel($line, $level)) {
                    continue;
                }

                // Filter by search term if specified
                if ($search && !str_contains($line, $search)) {
                    continue;
                }

                $filteredLines[] = $line;
            }

            // Apply pagination
            $totalLines = count($filteredLines);
            $paginatedLines = array_slice($filteredLines, $offset, $limit);

            return $this->json([
                'filename' => $filename,
                'total_lines' => $totalLines,
                'lines' => $paginatedLines,
                'limit' => $limit,
                'offset' => $offset
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving log file content', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filename' => $filename
            ]);

            return $this->json([
                'error' => 'Error retrieving log file content: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the latest errors from the log files
     */
    #[Route("/latest", name: "latest", methods: ["GET"])]
    public function getLatestErrors(string $dominio, Request $request): JsonResponse
    {

        try {
            $limit = $request->query->getInt('limit', 50);
            $level = $request->query->get('level', 'error');

            $filesystem = new Filesystem();

            // Check for tenant-specific log directory
            $tenantLogDir = $this->logDir . '/' . $dominio;
            $logDir = $filesystem->exists($tenantLogDir) ? $tenantLogDir : $this->logDir;

            if (!$filesystem->exists($logDir)) {
                return $this->json([
                    'error' => 'Log directory not found'
                ], 404);
            }

            $finder = new Finder();
            $finder->files()->in($logDir)->name('*.log')->sortByModifiedTime();

            $latestErrors = [];
            foreach ($finder as $file) {
                $content = file_get_contents($file->getRealPath());
                $lines = explode("\n", $content);

                foreach ($lines as $line) {
                    if (empty($line)) {
                        continue;
                    }

                    if ($this->lineContainsLevel($line, $level)) {
                        $latestErrors[] = [
                            'file' => $file->getFilename(),
                            'content' => $line
                        ];

                        if (count($latestErrors) >= $limit) {
                            break 2;
                        }
                    }
                }
            }

            return $this->json([
                'errors' => $latestErrors,
                'count' => count($latestErrors)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving latest errors', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->json([
                'error' => 'Error retrieving latest errors: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a log line contains the specified log level
     */
    private function lineContainsLevel(string $line, string $level): bool
    {
        $level = strtolower($level);
        return str_contains(strtolower($line), $level);
    }
}

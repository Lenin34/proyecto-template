<?php

namespace App\Controller\Api;

use App\Entity\App\User;
use App\Service\FileUploadService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api/files')]
class FileController extends AbstractController
{
    private TenantManager $tenantManager;
    private FileUploadService $fileUploadService;
    private Security $security;

    public function __construct(
        TenantManager $tenantManager,
        FileUploadService $fileUploadService,
        Security $security
    ) {
        $this->tenantManager = $tenantManager;
        $this->fileUploadService = $fileUploadService;
        $this->security = $security;
    }

    /**
     * Endpoint para servir archivos con nueva estructura: uploads/forms/{userId}/{formTemplateId}/{filename}
     */
    #[Route('/{userId}/{formTemplateId}/{filename}', name: 'api_files_serve_new', methods: ['GET'])]
    public function serveFile(string $dominio, int $userId, int $formTemplateId, string $filename): Response
    {
        try {
            error_log("🔍 FileController: Sirviendo archivo {$filename} para usuario {$userId}, formulario {$formTemplateId}");
            

            // Verificar autenticación
            $currentUser = $this->security->getUser();
            if (!$currentUser instanceof User) {
                error_log("❌ Usuario no autenticado");
                return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
            }

            // Verificar permisos: solo el propietario del archivo o admin puede acceder
            if ($currentUser->getId() !== $userId && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
                error_log("❌ Usuario {$currentUser->getId()} no tiene permisos para archivo de usuario {$userId}");
                return new Response('Forbidden', Response::HTTP_FORBIDDEN);
            }

            // Construir ruta del archivo
            $relativePath = '/forms/' . $userId . '/' . $formTemplateId . '/' . $filename;
            
            // Verificar que el archivo existe
            if (!$this->fileUploadService->fileExists($relativePath)) {
                error_log("❌ Archivo no encontrado: {$relativePath}");
                return new Response('File not found', Response::HTTP_NOT_FOUND);
            }

            // Obtener información del archivo
            $fileInfo = $this->fileUploadService->getFileInfo($relativePath);
            if (!$fileInfo) {
                error_log("❌ No se pudo obtener información del archivo: {$relativePath}");
                return new Response('File info not available', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Construir ruta completa
            $uploadsDir = $this->getParameter('uploads_directory');
            $fullPath = $uploadsDir . $relativePath;

            // Verificar seguridad del path
            $realFilePath = realpath($fullPath);
            $allowedDir = realpath($uploadsDir . '/forms/' . $userId . '/' . $formTemplateId);

            if (!$realFilePath || !$allowedDir || strpos($realFilePath, $allowedDir) !== 0) {
                error_log("❌ Path inseguro: {$fullPath}");
                return new Response('Invalid file path', Response::HTTP_FORBIDDEN);
            }

            // Leer contenido del archivo
            $fileContent = file_get_contents($realFilePath);
            if ($fileContent === false) {
                error_log("❌ Error leyendo archivo: {$realFilePath}");
                return new Response('Error reading file', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Crear respuesta con headers apropiados
            $response = new Response($fileContent);
            $response->headers->set('Content-Type', $fileInfo['mime_type']);
            $response->headers->set('Content-Length', (string) $fileInfo['file_size']);

            // Para imágenes, permitir visualización en el navegador
            if (strpos($fileInfo['mime_type'], 'image/') === 0) {
                $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
            } else {
                // Para otros archivos, forzar descarga
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            }

            // Headers de cache
            $response->headers->set('Cache-Control', 'private, max-age=3600');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

            error_log("✅ Archivo servido exitosamente: {$filename}");
            return $response;

        } catch (\Exception $e) {
            error_log("❌ Error sirviendo archivo: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            return new Response('Internal server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Endpoint para servir archivos con estructura antigua (compatibilidad): uploads/forms/{userId}/{filename}
     */
    #[Route('/legacy/{userId}/{filename}', name: 'api_files_serve_legacy', methods: ['GET'])]
    public function serveLegacyFile(string $dominio, int $userId, string $filename): Response
    {
        try {
            error_log("🔍 FileController: Sirviendo archivo legacy {$filename} para usuario {$userId}");
            

            // Verificar autenticación
            $currentUser = $this->security->getUser();
            if (!$currentUser instanceof User) {
                return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
            }

            // Verificar permisos
            if ($currentUser->getId() !== $userId && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
                return new Response('Forbidden', Response::HTTP_FORBIDDEN);
            }

            // Construir ruta del archivo legacy
            $uploadsDir = $this->getParameter('uploads_directory');
            $filePath = $uploadsDir . '/forms/' . $userId . '/' . $filename;

            // Verificar que el archivo existe
            if (!file_exists($filePath) || !is_file($filePath)) {
                return new Response('File not found', Response::HTTP_NOT_FOUND);
            }

            // Verificar seguridad del path
            $realFilePath = realpath($filePath);
            $allowedDir = realpath($uploadsDir . '/forms/' . $userId);

            if (!$realFilePath || !$allowedDir || strpos($realFilePath, $allowedDir) !== 0) {
                return new Response('Invalid file path', Response::HTTP_FORBIDDEN);
            }

            // Determinar tipo MIME
            $mimeType = mime_content_type($realFilePath);
            if (!$mimeType) {
                $mimeType = 'application/octet-stream';
            }

            // Leer contenido
            $fileContent = file_get_contents($realFilePath);
            if ($fileContent === false) {
                return new Response('Error reading file', Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Crear respuesta
            $response = new Response($fileContent);
            $response->headers->set('Content-Type', $mimeType);
            $response->headers->set('Content-Length', (string) filesize($realFilePath));

            if (strpos($mimeType, 'image/') === 0) {
                $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
            } else {
                $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
            }

            $response->headers->set('Cache-Control', 'private, max-age=3600');
            $response->headers->set('Expires', gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

            error_log("✅ Archivo legacy servido exitosamente: {$filename}");
            return $response;

        } catch (\Exception $e) {
            error_log("❌ Error sirviendo archivo legacy: " . $e->getMessage());
            return new Response('Internal server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Endpoint para obtener información de archivos de un usuario
     */
    #[Route('/info/{userId}', name: 'api_files_user_info', methods: ['GET'])]
    public function getUserFilesInfo(string $dominio, int $userId): JsonResponse
    {
        try {

            // Verificar autenticación
            $currentUser = $this->security->getUser();
            if (!$currentUser instanceof User) {
                return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            // Verificar permisos
            if ($currentUser->getId() !== $userId && !in_array('ROLE_ADMIN', $currentUser->getRoles())) {
                return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
            }

            $uploadsDir = $this->getParameter('uploads_directory');
            $userDir = $uploadsDir . '/forms/' . $userId;
            $files = [];

            if (is_dir($userDir)) {
                $this->scanDirectoryRecursive($userDir, $files, $userId);
            }

            return new JsonResponse([
                'user_id' => $userId,
                'total_files' => count($files),
                'files' => $files
            ]);

        } catch (\Exception $e) {
            error_log("❌ Error obteniendo info de archivos: " . $e->getMessage());
            return new JsonResponse(['error' => 'Internal server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function scanDirectoryRecursive(string $dir, array &$files, int $userId): void
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir . '/' . $item;
            if (is_file($fullPath)) {
                $relativePath = str_replace($this->getParameter('uploads_directory'), '', $fullPath);
                $pathParts = explode('/', trim($relativePath, '/'));
                
                $files[] = [
                    'filename' => $item,
                    'relative_path' => $relativePath,
                    'size' => filesize($fullPath),
                    'mime_type' => mime_content_type($fullPath) ?: 'unknown',
                    'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'form_template_id' => isset($pathParts[1]) ? (int)$pathParts[1] : null,
                    'is_image' => strpos(mime_content_type($fullPath) ?: '', 'image/') === 0
                ];
            } elseif (is_dir($fullPath)) {
                $this->scanDirectoryRecursive($fullPath, $files, $userId);
            }
        }
    }
}

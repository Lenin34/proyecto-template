<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Service\ApplicationErrorService;
use App\Enum\ErrorCodes\FileUploadErrorCodes;

class FileUploadService
{
    private string $uploadsDirectory;
    private array $allowedMimeTypes = [
        // Imágenes
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        // Documentos
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        // Audio
        'audio/mpeg',
        'audio/wav',
        'audio/mp4',
        // Video
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
    ];
    private string $maxFileSize = '10M';
    private ApplicationErrorService $applicationErrorService;

    public function __construct(string $uploadsDirectory, ApplicationErrorService $applicationErrorService)
    {
        $this->uploadsDirectory = $uploadsDirectory;
        $this->applicationErrorService = $applicationErrorService;
    }

    /**
     * Sube un archivo con estructura mejorada: uploads/{userId}/{formTemplateId}/
     */
    public function uploadFile(UploadedFile $file, int $userId, int $formTemplateId): ?array
    {
        error_log("🚀 FileUploadService: Iniciando upload de archivo");
        error_log("📄 Archivo: " . $file->getClientOriginalName() . " (" . $file->getSize() . " bytes)");
        error_log("👤 Usuario: {$userId}, Formulario: {$formTemplateId}");

        // Validar que el archivo sea válido
        if (!$file->isValid()) {
            error_log("❌ Archivo no válido: " . $file->getErrorMessage());
            $this->applicationErrorService->createError([
                'code' => FileUploadErrorCodes::FILE_UPLOAD_SERVICE_UPLOAD_FAILED->value,
                'message' => 'Invalid file uploaded'
            ], [
                'error_message' => 'Invalid file: ' . $file->getErrorMessage()
            ]);
            return null;
        }

        // Validar tipo de archivo
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            error_log("❌ Tipo de archivo no permitido: {$mimeType}");
            $this->applicationErrorService->createError([
                'code' => FileUploadErrorCodes::FILE_UPLOAD_SERVICE_INVALID_FILE_TYPE->value,
                'message' => 'Invalid file type'
            ], [
                'file_type' => $mimeType,
                'allowed_types' => $this->allowedMimeTypes
            ]);
            return null;
        }

        // Validar tamaño
        $maxFileSizeBytes = $this->convertToBytes($this->maxFileSize);
        if ($file->getSize() > $maxFileSizeBytes) {
            error_log("❌ Archivo muy grande: " . $file->getSize() . " bytes (máximo: {$maxFileSizeBytes})");
            $this->applicationErrorService->createError([
                'code' => FileUploadErrorCodes::FILE_UPLOAD_SERVICE_FILE_TOO_LARGE->value,
                'message' => 'File too large'
            ], [
                'file_size' => $file->getSize(),
                'max_file_size' => $this->maxFileSize
            ]);
            return null;
        }

        // Crear estructura de directorios: uploads/forms/{userId}/{formTemplateId}/
        $targetDirectory = $this->uploadsDirectory . '/forms/' . $userId . '/' . $formTemplateId;
        error_log("📁 Directorio objetivo: {$targetDirectory}");

        if (!is_dir($targetDirectory)) {
            error_log("📁 Creando directorio: {$targetDirectory}");
            if (!mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
                error_log("❌ Error creando directorio: {$targetDirectory}");
                $this->applicationErrorService->createError([
                    'code' => FileUploadErrorCodes::FILE_UPLOAD_SERVICE_DIRECTORY_CREATION_FAILED->value,
                    'message' => 'Failed to create upload directory'
                ], [
                    'directory' => $targetDirectory,
                    'error' => 'mkdir failed'
                ]);
                return null;
            }
            error_log("✅ Directorio creado exitosamente");
        } else {
            error_log("✅ Directorio ya existe");
        }

        // Generar nombre único
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $fileName = 'file_' . time() . '.' . $extension;
        $filePath = $targetDirectory . '/' . $fileName;

        // Obtener información del archivo ANTES de moverlo
        $fileSize = $file->getSize();

        error_log("📝 Nombre archivo: {$originalName} -> {$fileName}");

        try {
            // Mover archivo
            error_log("📦 Moviendo archivo a: {$filePath}");
            $file->move($targetDirectory, $fileName);
            error_log("✅ Archivo movido exitosamente");

            // Verificar que el archivo se guardó correctamente
            if (!file_exists($filePath)) {
                error_log("❌ El archivo no existe después de moverlo: {$filePath}");
                throw new \Exception("File was not saved properly");
            }

            // Crear información estructurada del archivo
            $fileInfo = [
                'file_path' => '/forms/' . $userId . '/' . $formTemplateId . '/' . $fileName,
                'original_name' => $originalName,
                'file_name' => $fileName,
                'file_size' => $fileSize, // Usar el tamaño obtenido antes del move
                'mime_type' => $mimeType,
                'uploaded_at' => date('Y-m-d H:i:s'),
                'user_id' => $userId,
                'form_template_id' => $formTemplateId,
                'extension' => $extension,
                'upload_success' => true
            ];

            error_log("✅ Upload completado: " . json_encode($fileInfo));
            error_log("📁 Archivo físico guardado en: " . $filePath);
            error_log("📊 Información del archivo: Nombre={$fileName}, Tamaño={$fileSize}, Tipo={$mimeType}");
            return $fileInfo;

        } catch (\Exception $e) {
            error_log("❌ Error durante upload: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            $this->applicationErrorService->createError([
                'code' => FileUploadErrorCodes::FILE_UPLOAD_SERVICE_UPLOAD_FAILED->value,
                'message' => 'File upload failed'
            ], [
                'file_name' => $fileName,
                'error_message' => $e->getMessage(),
                'target_directory' => $targetDirectory
            ]);
            return null;
        }
    }

    /**
     * Procesa archivos desde Android que pueden venir como content:// URIs
     */
    public function processAndroidFile($fileData, int $userId, int $formTemplateId): ?array
    {
        // Si es un content:// URI de Android, no podemos procesarlo directamente
        if (is_string($fileData) && strpos($fileData, 'content://') === 0) {
            // Registrar el problema para debugging
            error_log("⚠️ Android content URI detectado: {$fileData}");
            
            // Devolver información indicando que es un URI de Android no válido
            return [
                'error' => 'android_content_uri',
                'message' => 'Android content URI detected. File must be uploaded as multipart/form-data.',
                'original_uri' => $fileData,
                'user_id' => $userId,
                'form_template_id' => $formTemplateId
            ];
        }

        // Si es un UploadedFile válido, procesarlo normalmente
        if ($fileData instanceof UploadedFile) {
            return $this->uploadFile($fileData, $userId, $formTemplateId);
        }

        return null;
    }

    /**
     * Genera URL completa para acceder al archivo
     */
    public function generateFileUrl(string $relativePath, string $baseUrl): string
    {
        return $baseUrl . '/uploads' . $relativePath;
    }

    /**
     * Verifica si un archivo existe físicamente
     */
    public function fileExists(string $relativePath): bool
    {
        $fullPath = $this->uploadsDirectory . $relativePath;
        return file_exists($fullPath) && is_file($fullPath);
    }



    /**
     * Obtiene información de un archivo existente
     */
    public function getFileInfo(string $relativePath): ?array
    {
        $fullPath = $this->uploadsDirectory . $relativePath;
        
        if (!$this->fileExists($relativePath)) {
            return null;
        }

        $pathParts = explode('/', trim($relativePath, '/'));
        
        return [
            'file_path' => $relativePath,
            'file_name' => basename($relativePath),
            'file_size' => filesize($fullPath),
            'mime_type' => mime_content_type($fullPath) ?: 'application/octet-stream',
            'file_exists' => true,
            'user_id' => isset($pathParts[0]) ? (int)$pathParts[0] : null,
            'form_template_id' => isset($pathParts[1]) ? (int)$pathParts[1] : null,
        ];
    }

    /**
     * Convierte string de tamaño a bytes
     */
    private function convertToBytes(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;

        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }

        return $size;
    }

    /**
     * Elimina un archivo
     */
    public function deleteFile(string $relativePath): bool
    {
        $fullPath = $this->uploadsDirectory . $relativePath;
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }

    /**
     * Migra archivos de la estructura antigua a la nueva
     */
    public function migrateFile(string $oldPath, int $userId, int $formTemplateId): ?string
    {
        // Limpiar la ruta antigua
        $cleanOldPath = ltrim($oldPath, '/');

        // Si la ruta ya incluye /uploads/, quitarla
        if (strpos($cleanOldPath, 'uploads/') === 0) {
            $cleanOldPath = substr($cleanOldPath, 8); // Quitar "uploads/"
        }

        $oldFullPath = $this->uploadsDirectory . '/' . $cleanOldPath;

        error_log("🔍 Intentando migrar archivo:");
        error_log("  - Ruta original: {$oldPath}");
        error_log("  - Ruta limpia: {$cleanOldPath}");
        error_log("  - Ruta completa: {$oldFullPath}");
        error_log("  - Existe: " . (file_exists($oldFullPath) ? 'SÍ' : 'NO'));

        if (!file_exists($oldFullPath)) {
            error_log("❌ Archivo no encontrado: {$oldFullPath}");
            return null;
        }

        // Crear nueva estructura de directorios
        $newDirectory = $this->uploadsDirectory . '/forms/' . $userId . '/' . $formTemplateId;
        if (!is_dir($newDirectory)) {
            if (!mkdir($newDirectory, 0755, true)) {
                error_log("❌ No se pudo crear directorio: {$newDirectory}");
                return null;
            }
        }

        // Generar nuevo nombre
        $fileName = basename($oldPath);
        $newPath = '/forms/' . $userId . '/' . $formTemplateId . '/' . $fileName;
        $newFullPath = $this->uploadsDirectory . $newPath;

        error_log("📁 Nueva ubicación: {$newFullPath}");

        // Copiar archivo a nueva ubicación
        if (copy($oldFullPath, $newFullPath)) {
            error_log("✅ Archivo migrado exitosamente");
            return $newPath;
        } else {
            error_log("❌ Error copiando archivo");
            return null;
        }
    }
}

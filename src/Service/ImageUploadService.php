<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Service\ApplicationErrorService;
use App\Enum\ErrorCodes\ImageUploadErrorCodes;

class ImageUploadService
{
    private string $uploadsDirectory;
    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
    ];
    private string $maxFileSize = '5M';
    private ApplicationErrorService $applicationErrorService;

    public function __construct(string $uploadsDirectory, ApplicationErrorService $applicationErrorService)
    {
        $this->uploadsDirectory = $uploadsDirectory;
        $this->applicationErrorService = $applicationErrorService;
    }

    public function uploadImage(UploadedFile $file, string $subDirectory, ?int $userId = null, ?int $beneficiaryId = null): ?string
    {
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            $this->applicationErrorService->createError(ImageUploadErrorCodes:: IMAGE_UPLOAD_SERVICE_INVALID_FILE_TYPE, 
                [
                    'file_type' => $file->getMimeType(),
                ]
            );

            return null;
        }

        $maxFileSizeBytes = $this->convertToBytes($this->maxFileSize);
        if ($file->getSize() > $maxFileSizeBytes) {
            $this->applicationErrorService->createError(ImageUploadErrorCodes:: IMAGE_UPLOAD_SERVICE_FILE_TOO_LARGE, 
                [
                    'file_size' => $file->getSize(),
                    'max_file_size' => $this->maxFileSize,
                ]
            );

            return null;
        }

        // Determinar la estructura de directorio basada en los parámetros
        if ($userId && $beneficiaryId) {
            // Para beneficiarios: users/{userId}/beneficiaries/{beneficiaryId}/
            $targetDirectory = $this->uploadsDirectory . '/users/' . $userId . '/beneficiaries/' . $beneficiaryId;
            $relativePath = 'users/' . $userId . '/beneficiaries/' . $beneficiaryId;
        } elseif ($userId) {
            // Para usuarios: users/{userId}/profile/
            $targetDirectory = $this->uploadsDirectory . '/users/' . $userId . '/profile';
            $relativePath = 'users/' . $userId . '/profile';
        } else {
            // Fallback a la estructura anterior
            $targetDirectory = $this->uploadsDirectory . '/' . $subDirectory;
            $relativePath = $subDirectory;
        }

        if (!is_dir($targetDirectory)) {
            if (!mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_DIRECTORY_CREATION_FAILED, [
                    'directory' => $targetDirectory,
                ]);

                return null;
            }
        }

        $fileName = 'file_' . time() . '.' . $file->guessExtension();
        $filePath = $targetDirectory . '/' . $fileName;

        error_log("📸 ImageUploadService: Generando archivo: {$fileName}");
        error_log("📸 ImageUploadService: Ruta completa: {$filePath}");

        try {
            $success = $this->compressImage($file, $filePath, $file->getMimeType());
            if (!$success) {
                $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_IMAGE_COMPRESSION_FAILED, [
                    'file_name' => $fileName,
                ]);

                return null;
            }
        } catch (\Exception $e) {
            $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_UPLOAD_FAILED, [
                'file_name' => $fileName,
                'error_message' => $e->getMessage(),
            ]);

            return null;
        }

        $finalRelativePath = $relativePath . '/' . $fileName;
        error_log("📸 ImageUploadService: Imagen guardada exitosamente: {$finalRelativePath}");
        error_log("📸 ImageUploadService: Archivo físico en: {$filePath}");
        return $finalRelativePath;
    }

    private function compressImage(UploadedFile $file, string $filePath, string $mimeType): bool
    {
        try {
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file->getPathname());
                    if (!$image) {
                        $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_JPEG_COMPRESSION_FAILED, [
                            'file_path' => $file->getPathname(),
                        ]);

                        return false;
                    }

                    imagejpeg($image, $filePath, 75);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file->getPathname());
                    if (!$image) {
                        $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_PNG_COMPRESSION_FAILED, [
                            'file_path' => $file->getPathname(),
                        ]);

                        return false;
                    }

                    imagepng($image, $filePath, 6);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file->getPathname());
                    if (!$image) {
                        $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_GIF_COMPRESSION_FAILED, [
                            'file_path' => $file->getPathname(),
                        ]);

                        return false;
                    }

                    imagegif($image, $filePath);
                    break;
                default:
                    $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_FILE_TYPE_NOT_SUPPORTED, [
                        'file_type' => $mimeType,
                    ]);

                    return false;
            }

            imagedestroy($image);

            return true;
        } catch (\Throwable $e) {
            $this->applicationErrorService->createError(ImageUploadErrorCodes::IMAGE_UPLOAD_SERVICE_COMPRESSION_FAILED, [
                'file_name' => $file->getClientOriginalName(),
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function convertToBytes (string $size): int
    {
        $unit = strtoupper(substr($size, -1));
        $value = (int) substr($size, 0, -1);

        switch ($unit) {
            case 'K':
                return $value * 1024;
            case 'M':
                return $value * 1024 * 1024;
            case 'G':
                return $value * 1024 * 1024 * 1024;
            default:
                return $value;
        }
    }
}
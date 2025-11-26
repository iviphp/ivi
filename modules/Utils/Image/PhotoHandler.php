<?php

namespace Modules\Utils\Image;

use Exception;

class PhotoHandler
{
    public static function handleImages($files, $directory, $prefix = 'softadastra')
    {
        // 🛑 Vérifie que c’est un tableau valide
        if (!isset($files['tmp_name']) || !is_array($files['tmp_name']) || empty(array_filter($files['tmp_name']))) {
            throw new Exception("You haven't selected any images to upload.");
        }

        // 🛑 Limite du nombre de fichiers
        if (count($files['tmp_name']) > 20) {
            throw new Exception("You can only upload up to 20 images.");
        }

        // 📂 Création du répertoire si nécessaire
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new Exception("Unable to create upload directory.");
            }
        }

        $uploadedImages = [];
        $errors = [];

        foreach ($files['tmp_name'] as $key => $tmp_name) {
            $fileName = $files['name'][$key] ?? 'Unknown file';

            try {
                // ❗ Si aucun fichier sélectionné à cet index
                if (empty($tmp_name) || $files['error'][$key] === UPLOAD_ERR_NO_FILE) {
                    throw new Exception("No file selected.");
                }

                // Autre erreur de téléchargement
                if ($files['error'][$key] !== UPLOAD_ERR_OK) {
                    throw new Exception("Upload error for file: $fileName");
                }

                $file = [
                    'name' => $fileName,
                    'type' => $files['type'][$key],
                    'tmp_name' => $tmp_name,
                    'error' => $files['error'][$key],
                    'size' => $files['size'][$key]
                ];

                $uploadedImage = PhotoHandler::photo($file, $prefix, $directory);
                $uploadedImages[] = $uploadedImage;
            } catch (Exception $e) {
                $message = $e->getMessage();
                $decoded = json_decode($message, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($decoded['message'])) {
                    $errors[] = "File '$fileName': " . $decoded['message'];
                } else {
                    $errors[] = "File '$fileName': " . $message;
                }

                continue;
            }
        }

        // ❌ Nettoyage si au moins une erreur
        if (!empty($errors)) {
            foreach ($uploadedImages as $image) {
                @unlink($directory . '/' . $image);
            }

            throw new Exception(implode("\n", $errors));
        }

        return $uploadedImages;
    }

    static public function photo(array $file, string $prefix = 'softadastra', string $uploadDirectory = '')
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        $maxFileSize = 5 * 1024 * 1024; // 5 MB

        try {
            // Upload error
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception(json_encode([
                    'code' => 'UPLOAD_FAILED',
                    'message' => 'The file could not be uploaded. Please try again.'
                ]));
            }

            // File type check
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception(json_encode([
                    'code' => 'UPLOAD_TYPE_NOT_ALLOWED',
                    'message' => 'Unsupported file type. Allowed types: JPG, PNG, GIF, WEBP, BMP.'
                ]));
            }

            // File size check
            if ($file['size'] > $maxFileSize) {
                throw new Exception(json_encode([
                    'code' => 'UPLOAD_TOO_LARGE',
                    'message' => 'The file is too large. Maximum allowed size is 5 MB.'
                ]));
            }

            self::checkMemoryNeeds($file['tmp_name']);

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $uniqueName = uniqid($prefix . '_', true) . '.' . $extension;
            $uploadFilePath = rtrim($uploadDirectory, '/') . '/' . $uniqueName;

            $imageInfo = getimagesize($file['tmp_name']);
            if (
                $imageInfo[0] <= 1200 &&
                $imageInfo[1] <= 1200 &&
                $file['size'] <= 1024 * 1024
            ) {
                if (!move_uploaded_file($file['tmp_name'], $uploadFilePath)) {
                    throw new Exception(json_encode([
                        'code' => 'FILE_MOVE_ERROR',
                        'message' => 'Failed to save the uploaded image. Please try again.'
                    ]));
                }
                return $uniqueName;
            }

            switch ($file['type']) {
                case 'image/jpeg':
                    $image = imagecreatefromjpeg($file['tmp_name']);
                    break;
                case 'image/png':
                    $image = imagecreatefrompng($file['tmp_name']);
                    break;
                case 'image/gif':
                    $image = imagecreatefromgif($file['tmp_name']);
                    if (!imageistruecolor($image)) {
                        $image = imagepalettetotruecolor($image);
                    }
                    break;
                case 'image/webp':
                    $image = imagecreatefromwebp($file['tmp_name']);
                    break;
                case 'image/bmp':
                    $image = imagecreatefrombmp($file['tmp_name']);
                    break;
                default:
                    throw new Exception(json_encode([
                        'code' => 'UNSUPPORTED_IMAGE_FORMAT',
                        'message' => 'Unsupported image format.'
                    ]));
            }

            $image = self::resizeImage($image, 1200, 1200);

            switch ($file['type']) {
                case 'image/jpeg':
                    imagejpeg($image, $uploadFilePath, 75);
                    break;
                case 'image/png':
                    imagepng($image, $uploadFilePath, 7);
                    break;
                case 'image/gif':
                    imagegif($image, $uploadFilePath);
                    break;
                case 'image/webp':
                    imagewebp($image, $uploadFilePath, 75);
                    break;
                case 'image/bmp':
                    imagebmp($image, $uploadFilePath);
                    break;
            }

            imagedestroy($image);
            return $uniqueName;
        } catch (Exception $e) {
            // Re-throw or handle the error as needed
            $error = json_decode($e->getMessage(), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Optional: if used in an API controller, you could do something like:
                http_response_code(400); // or custom codes
                echo json_encode(['success' => false] + $error);
                exit;
            } else {
                // Fallback for unstructured exceptions
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'An unexpected error occurred.'
                ]);
                exit;
            }
        }
    }


    private static function resizeImage($image, int $maxWidth, int $maxHeight)
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width < 10 || $height < 10) {
            throw new Exception(json_encode([
                'code' => 'IMAGE_TOO_SMALL',
                'message' => 'The image is too small. Minimum size is 10x10 pixels.'
            ]));
        }

        // No resizing needed
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $image;
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = max(1, (int)round($width * $ratio));
        $newHeight = max(1, (int)round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        // Transparency handling
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);

        return $resized;
    }

    private static function checkMemoryNeeds($filePath)
    {
        $imageInfo = getimagesize($filePath);
        if (!$imageInfo) {
            throw new Exception(json_encode([
                'code' => 'INVALID_IMAGE',
                'message' => 'The uploaded file is not a valid image.'
            ]));
        }

        $memoryNeeded = $imageInfo[0] * $imageInfo[1] * ($imageInfo['bits'] / 8) * 1.7;

        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = 0;

        if (preg_match('/^(\d+)([KMG]?)$/i', $memoryLimit, $matches)) {
            $value = (int)$matches[1];
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'G':
                    $memoryLimitBytes = $value * 1024 * 1024 * 1024;
                    break;
                case 'M':
                    $memoryLimitBytes = $value * 1024 * 1024;
                    break;
                case 'K':
                    $memoryLimitBytes = $value * 1024;
                    break;
                default:
                    $memoryLimitBytes = $value;
                    break;
            }
        }

        if (memory_get_usage() + $memoryNeeded > $memoryLimitBytes) {
            throw new Exception(json_encode([
                'code' => 'INSUFFICIENT_MEMORY',
                'message' => 'There is not enough server memory to process this image.'
            ]));
        }
    }
}

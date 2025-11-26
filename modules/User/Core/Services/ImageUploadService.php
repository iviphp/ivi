<?php

declare(strict_types=1);

namespace Modules\User\Core\Services;

use Cloudinary\Api\Upload\UploadApi;
use Exception;
use Ivi\Http\JsonResponse;
use Modules\Utils\Image\PhotoHandler;
use Modules\User\Core\Repositories\UserRepository;

class ImageUploadService extends BaseService
{
    private UserSecurityService $userSecurityService;

    public function __construct(
        UserRepository $repository,
    ) {
        parent::__construct($repository);
    }

    public function updatePhoto(array $files): void
    {
        // 1) Auth
        $user = $this->getUserEntity();
        if (!$user || !$user->getId()) {
            (new JsonResponse(['error' => 'You must be logged in.'], 401))->send();
        }
        $userId = (int)$user->getId();

        // 2) Identifier le champ visé (on n’accepte qu’un seul à la fois)
        $hasPhoto = isset($files['photo']) && is_array($files['photo']);
        $hasCover = isset($files['cover_photo']) && is_array($files['cover_photo']);

        if (!$hasPhoto && !$hasCover) {
            (new JsonResponse(['error' => 'No image was uploaded.'], 400))->send();
        }
        if ($hasPhoto && $hasCover) {
            (new JsonResponse(['error' => 'Please upload either "photo" or "cover_photo", not both.'], 400))->send();
        }

        $field     = $hasPhoto ? 'photo' : 'cover_photo';
        $file      = $files[$field];
        $fileLabel = $field === 'photo' ? 'Profile photo' : 'Cover photo';
        $destDir   = $field === 'photo' ? 'public/images/profile' : 'public/images/cover';

        // 3) Vérification de l'upload PHP
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $map = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit.',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit.',
                UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            ];
            $msg = $map[$file['error']] ?? 'Upload error.';
            (new JsonResponse(['error' => $msg], 400))->send();
        }

        // 4) Validation basique (taille + MIME)
        $maxBytes = 5 * 1024 * 1024; // 5 Mo
        if (!isset($file['size']) || $file['size'] <= 0) {
            (new JsonResponse(['error' => 'Empty file.'], 400))->send();
        }
        if ($file['size'] > $maxBytes) {
            (new JsonResponse(['error' => 'File too large. Max 5 MB.'], 413))->send();
        }

        $tmpPath = $file['tmp_name'] ?? null;
        if (!$tmpPath || !is_uploaded_file($tmpPath)) {
            (new JsonResponse(['error' => 'Invalid temporary file.'], 400))->send();
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? @finfo_file($finfo, $tmpPath) : null;
        if ($finfo) @finfo_close($finfo);

        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!$mime || !in_array($mime, $allowed, true)) {
            (new JsonResponse(['error' => 'Unsupported image type. Allowed: JPG, PNG, WEBP.'], 400))->send();
        }

        // 5) Essayer Cloudinary
        $savedUrl = null;
        $savedPid = null;
        try {
            $folder   = defined('CLOUDINARY_FOLDER') ? CLOUDINARY_FOLDER : 'softadastra/users';
            $isAvatar = ($field === 'photo');

            $publicId = sprintf(
                '%s/%d/%s-%s',
                trim($folder, '/'),
                $userId,
                $isAvatar ? 'avatar' : 'cover',
                bin2hex(random_bytes(6))
            );

            $eager = $isAvatar
                ? [['width' => 512,  'height' => 512,  'crop' => 'fill', 'gravity' => 'auto']]
                : [['width' => 1600, 'height' => 600,  'crop' => 'fill', 'gravity' => 'auto']];

            $result = (new UploadApi())->upload($tmpPath, [
                'public_id'     => $publicId,
                'resource_type' => 'image',
                'overwrite'     => true,
                'invalidate'    => true,
                'context'       => ['caption' => $fileLabel, 'alt' => $fileLabel],
                'eager'         => $eager,
            ]);

            $originalUrl = $result['secure_url'] ?? null;
            $eagerUrl    = (!empty($result['eager'][0]['secure_url'])) ? $result['eager'][0]['secure_url'] : null;

            $savedUrl = $eagerUrl ?: $originalUrl;
            $savedPid = $result['public_id'] ?? null;

            if (!$savedUrl || !$savedPid) {
                throw new Exception('Cloudinary upload failed.');
            }
        } catch (\Throwable $e) {
            // 6) Fallback local
            try {
                $filename = $this->handleImage($file, $destDir, $field);
                $savedUrl = '/' . ltrim($filename, '/'); // chemin relatif pour le site
                $savedPid = null;
            } catch (\Throwable $ex) {
                (new JsonResponse([
                    'error' => 'Image upload failed on both Cloudinary and local.',
                    'cloudinary' => $e->getMessage(),
                    'local' => $ex->getMessage()
                ], 500))->send();
            }
        }

        // 7) Persistance en BDD
        $ok = $this->repository->updateField($userId, $field, $savedUrl, $savedPid);
        if (!$ok) {
            (new JsonResponse(['error' => 'Failed to save the new image path.'], 500))->send();
        }

        // 8) Réponse OK
        (new JsonResponse([
            'success'   => true,
            'field'     => $field,
            'url'       => $savedUrl,
            'public_id' => $savedPid,
            'message'   => $fileLabel . ' updated successfully.'
        ], 200))->send();
    }

    public static function handleImage($file, $directory, $prefix = 'softadastra')
    {
        return PhotoHandler::photo($file, $prefix, $directory);
    }
}

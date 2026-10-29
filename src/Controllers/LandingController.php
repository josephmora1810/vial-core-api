<?php

namespace App\Controllers;

use App\Application\Landing\DTOs\HomeGalleryResponseDto;
use App\Application\Landing\UseCases\{GetActiveHomeGallery, ManageHomeGallery};
use App\Core\{Request, Response};
use Exception;

class LandingController
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private GetActiveHomeGallery $getActiveHomeGallery,
        private ManageHomeGallery $manageHomeGallery
    ) {}

    /**
     * GET /api/v1/home-gallery
     * Público para la Landing Page de Vue.js (FR-36).
     */
    public function index(Request $_): void
    {
        try {
            $banners = $this->getActiveHomeGallery->execute();
            Response::json(HomeGalleryResponseDto::collectionToArray($banners), 200, 'success', 'Galería de banners consultada exitosamente.');
        } catch (Exception $e) {
            Response::json([], 500, 'error', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/admin/home-gallery
     * Lista todos los banners (activos e inactivos) para gestión administrativa.
     */
    public function adminIndex(Request $_): void
    {
        try {
            $banners = $this->manageHomeGallery->listAll();
            Response::json(HomeGalleryResponseDto::collectionToArray($banners), 200, 'success', 'Listado administrativo de galería consultado.');
        } catch (Exception $e) {
            Response::json([], 500, 'error', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/admin/home-gallery
     * Crea un nuevo banner y sube su imagen a S3.
     */
    public function store(Request $request): void
    {
        $title = $request->input('title');
        if (empty($title)) {
            Response::json(['title' => 'El título es obligatorio.'], 422, 'fail', 'Validación fallida.');
            return;
        }

        $upload = $this->processImageUpload();
        if ($upload['error'] !== null) {
            Response::json($upload['error']['data'], 422, 'fail', $upload['error']['message']);
            return;
        }

        try {
            $item = $this->manageHomeGallery->create(
                title: (string)$title,
                subtitle: $request->input('subtitle'),
                description: $request->input('description'),
                buttonText: $request->input('button_text'),
                buttonLink: $request->input('button_link'),
                orderIndex: (int)($request->input('order_index', 0)),
                isActive: filter_var($request->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
                localImageFilePath: $upload['path'],
                imageUrl: $request->input('image_url')
            );

            Response::json(HomeGalleryResponseDto::fromEntity($item)->toArray(), 201, 'success', 'Banner de galería creado exitosamente.');
        } catch (Exception $e) {
            $this->respondWithException($e);
        }
    }

    /**
     * PUT /api/v1/admin/home-gallery/{id}
     * Actualiza un banner existente.
     */
    public function update(Request $request, string|int $id): void
    {
        $id = (int)$id;

        $upload = $this->processImageUpload();
        if ($upload['error'] !== null) {
            Response::json($upload['error']['data'], 422, 'fail', $upload['error']['message']);
            return;
        }

        try {
            $item = $this->manageHomeGallery->update(
                id: $id,
                title: $request->input('title'),
                subtitle: $request->input('subtitle'),
                description: $request->input('description'),
                buttonText: $request->input('button_text'),
                buttonLink: $request->input('button_link'),
                orderIndex: $request->input('order_index') !== null ? (int)$request->input('order_index') : null,
                isActive: $request->input('is_active') !== null ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN) : null,
                localImageFilePath: $upload['path'],
                imageUrl: $request->input('image_url')
            );

            Response::json(HomeGalleryResponseDto::fromEntity($item)->toArray(), 200, 'success', 'Banner de galería actualizado exitosamente.');
        } catch (Exception $e) {
            $this->respondWithException($e);
        }
    }

    /**
     * DELETE /api/v1/admin/home-gallery/{id}
     * Elimina un banner y sus imágenes.
     */
    public function destroy(Request $_, string|int $id): void
    {
        $id = (int)$id;

        try {
            $result = $this->manageHomeGallery->delete($id);
            Response::json($result->toArray(), 200, 'success', 'Banner eliminado exitosamente.');
        } catch (Exception $e) {
            $this->respondWithException($e);
        }
    }

    /**
     * Valida (extensión + MIME real) y mueve la imagen subida ($_FILES['image']) a storage.
     *
     * @return array{path: ?string, error: ?array{data: array<string, string>, message: string}}
     */
    private function processImageUpload(): array
    {
        if (empty($_FILES['image']['tmp_name'])) {
            return ['path' => null, 'error' => null];
        }

        $uploaded = $_FILES['image'];
        $ext = strtolower(pathinfo((string)$uploaded['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return ['path' => null, 'error' => [
                'data' => ['image' => 'Extensión de archivo no permitida. Formatos aceptados: JPG, PNG, WEBP.'],
                'message' => 'Formato no permitido.',
            ]];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $uploaded['tmp_name']);

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return ['path' => null, 'error' => [
                'data' => ['image' => 'El archivo adjunto no es una imagen válida.'],
                'message' => 'MIME type inválido.',
            ]];
        }

        $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }
        $targetPath = $uploadsDir . '/banner_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $moved = is_uploaded_file($uploaded['tmp_name'])
            ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
            : copy($uploaded['tmp_name'], $targetPath);

        return ['path' => $moved ? $targetPath : null, 'error' => null];
    }

    private function respondWithException(Exception $e): void
    {
        $code = $e->getCode();
        $httpCode = (\is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
        Response::json([], $httpCode, 'fail', $e->getMessage());
    }
}

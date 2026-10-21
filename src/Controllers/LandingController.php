<?php

namespace App\Controllers;

use App\Application\Landing\UseCases\GetActiveHomeGallery;
use App\Application\Landing\UseCases\ManageHomeGallery;
use App\Core\Request;
use App\Core\Response;
use Exception;

class LandingController
{
    public function __construct(
        private GetActiveHomeGallery $getActiveHomeGallery,
        private ManageHomeGallery $manageHomeGallery
    ) {}

    /**
     * GET /api/v1/home-gallery
     * Público para la Landing Page de Vue.js (FR-36).
     */
    public function index(Request $request): void
    {
        try {
            $banners = $this->getActiveHomeGallery->execute();
            Response::json($banners, 200, 'success', 'Galería de banners consultada exitosamente.');
        } catch (Exception $e) {
            Response::json([], 500, 'error', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/admin/home-gallery
     * Lista todos los banners (activos e inactivos) para gestión administrativa.
     */
    public function adminIndex(Request $request): void
    {
        try {
            $banners = $this->manageHomeGallery->listAll();
            Response::json($banners, 200, 'success', 'Listado administrativo de galería consultado.');
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

        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploaded = $_FILES['image'];
            $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExtensions, true)) {
                Response::json(['image' => 'Extensión de archivo no permitida. Formatos aceptados: JPG, PNG, WEBP.'], 422, 'fail', 'Formato no permitido.');
                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $uploaded['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowedMimes, true)) {
                Response::json(['image' => 'El archivo adjunto no es una imagen válida.'], 422, 'fail', 'MIME type inválido.');
                return;
            }

            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetPath = $uploadsDir . '/banner_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $moved = is_uploaded_file($uploaded['tmp_name'])
                ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
                : copy($uploaded['tmp_name'], $targetPath);

            if ($moved) {
                $imagePath = $targetPath;
            }
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
                localImageFilePath: $imagePath,
                imageUrl: $request->input('image_url')
            );

            Response::json($item, 201, 'success', 'Banner de galería creado exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/admin/home-gallery/{id}
     * Actualiza un banner existente.
     */
    public function update(Request $request, string|int $id): void
    {
        $id = (int)$id;

        $imagePath = null;
        if (!empty($_FILES['image']['tmp_name'])) {
            $uploaded = $_FILES['image'];
            $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExtensions, true)) {
                Response::json(['image' => 'Extensión de archivo no permitida. Formatos aceptados: JPG, PNG, WEBP.'], 422, 'fail', 'Formato no permitido.');
                return;
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $uploaded['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowedMimes, true)) {
                Response::json(['image' => 'El archivo adjunto no es una imagen válida.'], 422, 'fail', 'MIME type inválido.');
                return;
            }

            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            $targetPath = $uploadsDir . '/banner_' . bin2hex(random_bytes(6)) . '.' . $ext;
            $moved = is_uploaded_file($uploaded['tmp_name'])
                ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
                : copy($uploaded['tmp_name'], $targetPath);

            if ($moved) {
                $imagePath = $targetPath;
            }
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
                localImageFilePath: $imagePath,
                imageUrl: $request->input('image_url')
            );

            Response::json($item, 200, 'success', 'Banner de galería actualizado exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/admin/home-gallery/{id}
     * Elimina un banner y sus imágenes.
     */
    public function destroy(Request $request, string|int $id): void
    {
        $id = (int)$id;

        try {
            $this->manageHomeGallery->delete($id);
            Response::json(['deleted' => true, 'id' => $id], 200, 'success', 'Banner eliminado exitosamente.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }
}

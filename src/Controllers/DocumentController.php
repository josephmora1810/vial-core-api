<?php

namespace App\Controllers;

use App\Application\Auth\UseCases\GetKycStatus;
use App\Application\Auth\UseCases\ReviewKycDocument;
use App\Application\Auth\UseCases\UploadKycDocument;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Users\Entities\User;
use Exception;

class DocumentController
{
    public function __construct(
        private UploadKycDocument $uploadKycDocument,
        private GetKycStatus $getKycStatus,
        private ?ReviewKycDocument $reviewKycDocument = null
    ) {}

    /**
     * POST /api/v1/documents/upload
     * Carga o reenvía un documento KYC (Cédula, Pasaporte, Carnet de Circulación, etc.)
     */
    public function upload(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        $documentType = $request->input('document_type');
        if (empty($documentType)) {
            Response::json(['document_type' => 'El tipo de documento es obligatorio.'], 422, 'fail', 'Datos de validación inválidos.');
            return;
        }

        $vehicleId = $request->input('vehicle_id') !== null ? (int)$request->input('vehicle_id') : null;

        // CWE-73 / CWE-59: Rechazar rutas de archivos locales. Solo permitir subidas vía $_FILES
        if (empty($_FILES['document']['tmp_name'])) {
            $msg = $request->fileErrorMessage('document') ?? 'Debe adjuntar el archivo o comprobante del documento mediante subida de archivo (multipart/form-data).';
            Response::json(['document' => $msg], 422, 'fail', $msg);
            return;
        }

        $uploaded = $_FILES['document'];
        $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($ext, $allowedExtensions, true)) {
            Response::json(['document' => 'Extensión de archivo no permitida. Formatos aceptados: JPG, JPEG, PNG, PDF.'], 422, 'fail', 'Formato no permitido.');
            return;
        }

        // CWE-434: Validación estricta del tipo MIME real del binario
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $uploaded['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($mime, $allowedMimes, true)) {
            Response::json(['document' => 'El archivo adjunto no posee un formato permitido (JPEG, PNG o PDF).'], 422, 'fail', 'MIME type no permitido.');
            return;
        }

        $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }
        $targetName = sprintf('doc_%d_%s_%s.%s', $user->getId(), $documentType, bin2hex(random_bytes(8)), $ext);
        $targetPath = $uploadsDir . '/' . $targetName;

        $moved = is_uploaded_file($uploaded['tmp_name'])
            ? move_uploaded_file($uploaded['tmp_name'], $targetPath)
            : copy($uploaded['tmp_name'], $targetPath);

        if (!$moved) {
            Response::json([], 500, 'error', 'Error al almacenar el archivo en el servidor.');
            return;
        }

        $filePath = $targetPath;

        try {
            $result = $this->uploadKycDocument->execute(
                userId: $user->getId(),
                rawDocumentType: (string)$documentType,
                filePath: $filePath,
                vehicleId: $vehicleId
            );

            Response::json($result, 201, 'success', 'Documento procesado con éxito.');
        } catch (Exception $e) {
            $code = $e->getCode();
            $httpCode = (is_int($code) && $code >= 400 && $code < 600) ? $code : 400;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/documents/kyc-status
     * Retorna el estado actual del proceso de verificación KYC del usuario y sus vehículos
     */
    public function status(Request $request): void
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null || $user->getId() === null) {
            Response::json([], 401, 'fail', 'No autorizado.');
            return;
        }

        try {
            $status = $this->getKycStatus->execute($user->getId());
            Response::json($status, 200, 'success', 'Estado KYC consultado con éxito.');
        } catch (Exception $e) {
            Response::json([], 400, 'fail', $e->getMessage());
        }
    }

    /**
     * GET /api/v1/admin/documents
     * Lista documentos KYC filtrables por ?status=PENDING|VERIFIED|REJECTED (por defecto PENDING).
     */
    public function adminIndex(Request $request): void
    {
        if ($this->reviewKycDocument === null) {
            Response::json([], 501, 'error', 'Revisión de documentos no configurada.');
            return;
        }

        $status = $request->query('status') !== null ? (string)$request->query('status') : 'PENDING';
        if (strtoupper($status) === 'ALL') {
            $status = null;
        }

        try {
            $docs = $this->reviewKycDocument->listByStatus($status);
            Response::json($docs, 200, 'success', 'Documentos KYC consultados exitosamente.');
        } catch (Exception $e) {
            Response::json([], 400, 'fail', $e->getMessage());
        }
    }

    /**
     * POST /api/v1/admin/documents/{id}/review
     * Aprueba (VERIFIED) o rechaza (REJECTED) la imagen de un documento KYC (identidad o vehículo).
     */
    public function adminReview(Request $request, string $id): void
    {
        if ($this->reviewKycDocument === null) {
            Response::json([], 501, 'error', 'Revisión de documentos no configurada.');
            return;
        }

        /** @var User|null $admin */
        $admin = $request->user();
        $decision = (string)($request->input('status') ?? '');
        $reason = $request->input('reason') !== null ? (string)$request->input('reason') : null;

        try {
            $result = $this->reviewKycDocument->execute(
                documentId: (int)$id,
                rawDecision: $decision,
                reason: $reason,
                adminUserId: $admin?->getId()
            );
            Response::json($result, 200, 'success', 'Documento KYC revisado exitosamente.');
        } catch (Exception $e) {
            $code = (int)$e->getCode();
            $httpCode = ($code >= 400 && $code < 600) ? $code : 422;
            Response::json([], $httpCode, 'fail', $e->getMessage());
        }
    }
}

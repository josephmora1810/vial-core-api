<?php

namespace App\Controllers;

use App\Application\UseCases\GetKycStatus;
use App\Application\UseCases\UploadKycDocument;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Entities\User;
use Exception;

class DocumentController
{
    public function __construct(
        private UploadKycDocument $uploadKycDocument,
        private GetKycStatus $getKycStatus
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

        // Soporte para carga de archivo multipart o ruta/base64
        $filePath = null;
        if (!empty($_FILES['document']['tmp_name'])) {
            $uploaded = $_FILES['document'];
            $ext = pathinfo($uploaded['name'], PATHINFO_EXTENSION);
            $uploadsDir = dirname(__DIR__, 2) . '/storage/documents';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }
            $targetName = sprintf('doc_%d_%s_%s.%s', $user->getId(), $documentType, bin2hex(random_bytes(8)), $ext ?: 'jpg');
            $targetPath = $uploadsDir . '/' . $targetName;
            if (move_uploaded_file($uploaded['tmp_name'], $targetPath)) {
                $filePath = $targetPath;
            }
        } elseif (!empty($request->input('file_path'))) {
            $filePath = (string)$request->input('file_path');
        }

        if (empty($filePath)) {
            $msg = $request->fileErrorMessage('document') ?? 'Debe adjuntar el archivo o comprobante del documento.';
            Response::json(['document' => $msg], 422, 'fail', $msg);
            return;
        }

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
}

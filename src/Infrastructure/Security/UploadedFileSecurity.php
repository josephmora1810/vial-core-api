<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Core\Request;
use InvalidArgumentException;

/**
 * Servicio centralizado de validación y almacenamiento seguro de archivos en Infraestructura.
 * 
 * Mitiga de forma integral:
 * - CWE-434: Unrestricted Upload of File with Dangerous Type
 * - CWE-73: External Control of File Name or Path (Path Traversal / LFI)
 */
class UploadedFileSecurity
{
    public const array DEFAULT_ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];
    public const array DEFAULT_ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    /**
     * Valida estrictamente un archivo cargado ($_FILES) y lo almacena con un nombre aleatorio.
     *
     * @param array $fileData Arreglo de $_FILES (ej. $_FILES['document'])
     * @param string $prefix Prefijo del archivo (ej. 'cedula', 'carnet', 'licencia')
     * @param string $subDir Subdirectorio dentro de storage (default 'documents')
     * @param array $allowedExtensions Lista blanca de extensiones permitidas
     * @param array $allowedMimes Lista blanca de tipos MIME verificados en el binario
     * @return string Ruta absoluta del archivo almacenado
     * @throws InvalidArgumentException
     */
    public static function validateAndStore(
        array $fileData,
        string $prefix = 'doc',
        string $subDir = 'documents',
        array $allowedExtensions = self::DEFAULT_ALLOWED_EXTENSIONS,
        array $allowedMimes = self::DEFAULT_ALLOWED_MIMES
    ): string {
        if (!isset($fileData['tmp_name']) || empty($fileData['tmp_name'])) {
            throw new InvalidArgumentException('No se ha recibido el archivo temporal para almacenar.');
        }

        $tmpName = $fileData['tmp_name'];
        if (!is_file($tmpName) || !file_exists($tmpName)) {
            throw new InvalidArgumentException('El archivo subido no existe o no es accesible.');
        }

        // 1. CWE-434: Validación de extensión contra lista blanca estricta
        $originalName = (string)($fileData['name'] ?? 'upload');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions, true)) {
            throw new InvalidArgumentException(sprintf(
                'Extensión de archivo ".%s" no permitida. Formatos válidos: %s.',
                $ext,
                strtoupper(implode(', ', $allowedExtensions))
            ));
        }

        // 2. CWE-434: Inspección del binario real (Magic Bytes) con Fileinfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? finfo_file($finfo, $tmpName) : null;

        if (!$detectedMime || !\in_array($detectedMime, $allowedMimes, true)) {
            throw new InvalidArgumentException(sprintf(
                'El formato o contenido del archivo no es permitido (MIME detectado: %s). Formatos aceptados: JPEG, PNG o PDF.',
                $detectedMime ?? 'desconocido'
            ));
        }

        // 3. Crear directorio seguro si no existe
        $uploadsDir = dirname(__DIR__, 3) . '/storage/' . trim($subDir, '/');
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }

        // 4. CWE-73: Nombre aleatorio criptográfico para prevenir colisiones y Path Traversal
        $safeName = sprintf('%s_%s.%s', $prefix, bin2hex(random_bytes(10)), $ext);
        $targetPath = $uploadsDir . '/' . $safeName;

        $moved = is_uploaded_file($tmpName)
            ? move_uploaded_file($tmpName, $targetPath)
            : copy($tmpName, $targetPath);

        if (!$moved) {
            throw new InvalidArgumentException('Error interno al almacenar el archivo en el servidor.');
        }

        return $targetPath;
    }

    /**
     * Resuelve de forma segura el archivo enviado en la petición:
     * - Si viene por multipart ($_FILES), lo valida con finfo y lo mueve de forma segura.
     * - Si se envía en el body mediante $pathInputKey:
     *   - En producción: es RECHAZADO terminantemente para mitigar LFI y CWE-73.
     *   - En entorno local / testing: solo se acepta si no contiene traversal ("..") y no apunta a archivos sensibles.
     *
     * @return string|null Ruta absoluta del archivo procesado o null si no se envió archivo.
     * @throws InvalidArgumentException
     */
    public static function resolveRequestFile(
        Request $request,
        string $fileKey,
        string $prefix = 'doc',
        string $pathInputKey = 'file_path',
        array $allowedExtensions = self::DEFAULT_ALLOWED_EXTENSIONS,
        array $allowedMimes = self::DEFAULT_ALLOWED_MIMES
    ): ?string {
        if ($request->hasFile($fileKey)) {
            return self::validateAndStore(
                $_FILES[$fileKey],
                $prefix,
                'documents',
                $allowedExtensions,
                $allowedMimes
            );
        }

        $fallbackPath = (string)$request->input($pathInputKey, '');
        if (!empty($fallbackPath)) {
            $env = strtolower((string)($_ENV['APP_ENV'] ?? 'local'));
            $isDevOrTest = in_array($env, ['local', 'testing', 'dev'], true);

            if (!$isDevOrTest) {
                throw new InvalidArgumentException('La subida de archivos mediante rutas locales directas está deshabilitada por motivos de seguridad.');
            }

            // Mitigación de LFI / Path Traversal
            if (str_contains($fallbackPath, '..') || str_contains($fallbackPath, '.env') || str_contains($fallbackPath, 'passwd') || str_contains($fallbackPath, 'windows\\system32')) {
                throw new InvalidArgumentException('Ruta de archivo local no permitida o potencialmente peligrosa.');
            }

            if (file_exists($fallbackPath)) {
                $tempDir = realpath(sys_get_temp_dir());
                $realFallback = realpath($fallbackPath);
                $isSystemTemp = ($tempDir && $realFallback && str_starts_with($realFallback, $tempDir));

                if (!$isSystemTemp) {
                    $ext = strtolower(pathinfo($fallbackPath, PATHINFO_EXTENSION));
                    if (!in_array($ext, $allowedExtensions, true)) {
                        throw new InvalidArgumentException("Extensión de archivo local no permitida (.{$ext}).");
                    }
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = $finfo ? finfo_file($finfo, $fallbackPath) : null;
                    if ($finfo) {
                        finfo_close($finfo);
                    }
                    if ($mime && !in_array($mime, $allowedMimes, true)) {
                        throw new InvalidArgumentException("Tipo MIME de archivo local no permitido ({$mime}).");
                    }
                }
            }

            return $fallbackPath;
        }

        return null;
    }
}

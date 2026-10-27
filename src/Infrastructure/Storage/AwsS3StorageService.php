<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Domain\Shared\Services\{IntegrationLoggerInterface, StorageServiceInterface};
use App\Infrastructure\Logging\IntegrationLogger;
use Throwable;

class AwsS3StorageService implements StorageServiceInterface
{
    private string $accessKey;
    private string $secretKey;
    private string $region;
    private string $bucket;
    private string $baseUrl;
    private IntegrationLoggerInterface $logger;

    public function __construct(
        ?string $accessKey = null,
        ?string $secretKey = null,
        ?string $region = null,
        ?string $bucket = null,
        ?string $baseUrl = null,
        ?IntegrationLoggerInterface $logger = null
    ) {
        $this->accessKey = $accessKey ?? (string)($_ENV['AWS_ACCESS_KEY_ID'] ?? '');
        $this->secretKey = $secretKey ?? (string)($_ENV['AWS_SECRET_ACCESS_KEY'] ?? '');
        $this->region    = $region ?? (string)($_ENV['AWS_DEFAULT_REGION'] ?? 'us-east-1');
        $this->bucket    = $bucket ?? (string)($_ENV['AWS_BUCKET'] ?? 'ubik-go');
        $this->baseUrl   = rtrim($baseUrl ?? (string)($_ENV['AWS_URL'] ?? "https://{$this->bucket}.s3.{$this->region}.amazonaws.com"), '/');
        $this->logger    = $logger ?? new IntegrationLogger();
    }

    public function upload(string $localFilePath, string $storageKey, ?string $contentType = null): string
    {
        if (!file_exists($localFilePath) || !is_readable($localFilePath)) {
            throw new AwsS3StorageException("El archivo local no existe o no se puede leer: {$localFilePath}");
        }

        $rawContent = file_get_contents($localFilePath);
        if ($rawContent === false) {
            throw new AwsS3StorageException("No se pudo leer el contenido del archivo: {$localFilePath}");
        }

        $contentType ??= $this->detectMimeType($localFilePath);
        $cleanContent = $this->stripExifIfApplicable($rawContent, $contentType);

        $storageKey = ltrim($storageKey, '/');
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $endpoint = "https://{$host}/{$storageKey}";

        $start = microtime(true);
        $contentHash = hash('sha256', $cleanContent);
        $headers = $this->buildAwsV4Headers('PUT', '/' . $storageKey, $host, $contentHash, $contentType);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $cleanContent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $durationMs = (microtime(true) - $start) * 1000;

        $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        $errorMessage = $isSuccess ? null : ($curlError ?: ($response ?: "HTTP {$httpCode}"));

        $this->logger->log(
            service: 's3',
            operation: 'upload_document',
            url: $endpoint,
            statusCode: $httpCode,
            durationMs: $durationMs,
            requestPayload: [
                'storage_key'  => $storageKey,
                'content_type' => $contentType,
                'bytes'        => strlen($cleanContent),
            ],
            responsePayload: $isSuccess ? 'UPLOAD_OK' : substr((string)$response, 0, 500),
            error: $errorMessage
        );

        if (!$isSuccess) {
            throw new AwsS3StorageException("Fallo al subir a AWS S3 ({$endpoint}): HTTP {$httpCode} - {$errorMessage}");
        }

        return "{$this->baseUrl}/{$storageKey}";
    }

    public function delete(string $storageKey): bool
    {
        $storageKey = ltrim($storageKey, '/');
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $endpoint = "https://{$host}/{$storageKey}";

        $start = microtime(true);
        $contentHash = hash('sha256', '');
        $headers = $this->buildAwsV4Headers('DELETE', '/' . $storageKey, $host, $contentHash);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $durationMs = (microtime(true) - $start) * 1000;

        $isSuccess = ($httpCode === 204 || $httpCode === 200);

        $this->logger->log(
            service: 's3',
            operation: 'delete_object',
            url: $endpoint,
            statusCode: $httpCode,
            durationMs: $durationMs,
            requestPayload: ['storage_key' => $storageKey],
            responsePayload: $isSuccess ? 'DELETE_OK' : substr((string)$response, 0, 500),
            error: $isSuccess ? null : "HTTP {$httpCode}"
        );

        return $isSuccess;
    }

    public function exists(string $storageKey): bool
    {
        $storageKey = ltrim($storageKey, '/');
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $endpoint = "https://{$host}/{$storageKey}";

        $contentHash = hash('sha256', '');
        $headers = $this->buildAwsV4Headers('HEAD', '/' . $storageKey, $host, $contentHash);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $httpCode === 200;
    }

    /**
     * Genera las cabeceras firmadas con el protocolo AWS Signature Version 4 (AWS4-HMAC-SHA256).
     *
     * @return list<string>
     */
    private function buildAwsV4Headers(
        string $method,
        string $canonicalUri,
        string $host,
        string $contentHash,
        ?string $contentType = null
    ): array {
        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        if ($contentType !== null) {
            $canonicalHeaders = "content-type:{$contentType}\nhost:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$amzDate}\n";
            $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';
        } else {
            $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$amzDate}\n";
            $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        }

        $canonicalRequest = "{$method}\n{$canonicalUri}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$contentHash}";

        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$dateStamp}/{$this->region}/s3/aws4_request";
        $stringToSign = "{$algorithm}\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kSecret = 'AWS4' . $this->secretKey;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "{$algorithm} Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $headers = [];
        if ($contentType !== null) {
            $headers[] = 'Content-Type: ' . $contentType;
        }
        $headers[] = 'Host: ' . $host;
        $headers[] = 'x-amz-date: ' . $amzDate;
        $headers[] = 'x-amz-content-sha256: ' . $contentHash;
        $headers[] = 'Authorization: ' . $authorization;

        return $headers;
    }

    /**
     * Limpia metadatos EXIF de imágenes JPEG/PNG para privacidad (NFR-17)
     */
    private function stripExifIfApplicable(string $content, string $contentType): string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
            return $content;
        }

        if (!str_contains($contentType, 'jpeg') && !str_contains($contentType, 'jpg') && !str_contains($contentType, 'png')) {
            return $content;
        }

        try {
            $img = @imagecreatefromstring($content);
            if ($img === false) {
                return $content;
            }

            ob_start();
            if (str_contains($contentType, 'png') && function_exists('imagepng')) {
                imagealphablending($img, false);
                imagesavealpha($img, true);
                imagepng($img, null, 8);
            } else {
                imagejpeg($img, null, 90);
            }
            $cleanData = ob_get_clean();

            return ($cleanData !== false && strlen($cleanData) > 0) ? $cleanData : $content;
        } catch (Throwable) {
            return $content;
        }
    }

    private function detectMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream'
        };
    }
}

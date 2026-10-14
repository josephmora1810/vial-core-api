<?php

namespace App\Infrastructure\Services;

use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Services\DocumentExtractorInterface;
use App\Domain\ValueObjects\BirthDate;
use App\Domain\ValueObjects\ExtractedIdentity;
use App\Domain\ValueObjects\LastNames;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Surnames;
use App\Infrastructure\Http\HttpClientInterface;
use DateTimeImmutable;
use Throwable;

/**
 * Adaptador de Infraestructura que consume el microservicio de extracción de documentos
 * (La Venezolana Document Extraction API) y produce Value Objects de Dominio.
 */
class LaVenezolanaDocumentExtractor implements DocumentExtractorInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private ?string $apiKey = null
    ) {}

    public function extractIdentityCard(string $filePath): ExtractedIdentity
    {
        $url = rtrim($this->baseUrl, '/') . '/extractions';

        $headers = [];
        if (!empty($this->apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        try {
            // Enviamos la imagen vía multipart/form-data usando nuestro cliente cURL puro
            $response = $this->httpClient->postMultipart(
                url: $url,
                fields: ['document_type' => 'identity_card'],
                files: ['document' => $filePath],
                headers: $headers
            );
        } catch (Throwable $e) {
            throw new DocumentExtractionException(
                "No se pudo establecer comunicación con el microservicio OCR: {$e->getMessage()}",
                503,
                $e
            );
        }

        // Si el microservicio respondió con error HTTP (4xx o 5xx)
        if (!$response->isSuccessful()) {
            $json = $response->json();
            $detail = $json['detail'] ?? 'El documento no pudo ser procesado o no es válido.';
            throw new DocumentExtractionException("Error en extracción documental: {$detail}", 422);
        }

        $data = $response->json();

        // Validamos que el resultado operativo sea de éxito
        if (($data['status'] ?? '') !== 'success') {
            throw new DocumentExtractionException("La imagen no corresponde a una cédula legible o válida.", 422);
        }

        $fields = $data['fields'] ?? [];

        $rawDocNumber = $fields['document_number']['value'] ?? null;
        $rawGivenNames = $fields['given_names']['value'] ?? null;
        $rawSurnames = $fields['surnames']['value'] ?? null;
        $rawDob = $fields['date_of_birth']['value'] ?? null;
        $confidence = (float)($fields['document_number']['confidence'] ?? 0.0);

        if (empty($rawDocNumber) || empty($rawGivenNames) || empty($rawSurnames)) {
            throw new DocumentExtractionException("No se pudieron extraer los datos patronímicos requeridos del documento.", 422);
        }

        // Instanciamos los Value Objects del Dominio (aquí se autovalidan)
        try {
            $nationalId = new NationalId($rawDocNumber);
            $names = new Names($rawGivenNames);
            $lastNames = new Surnames($rawSurnames);
            $birthDate = !empty($rawDob) ? new BirthDate($rawDob) : null;
        } catch (Throwable $e) {
            throw new DocumentExtractionException("Los datos extraídos del documento tienen formato inválido: {$e->getMessage()}", 422, $e);
        }

        return new ExtractedIdentity(
            nationalId: $nationalId,
            names: $names,
            surnames: $lastNames,
            birthDate: $birthDate,
            confidenceScore: $confidence,
            status: $data['status'],
            rawPayload: $data
        );
    }
}
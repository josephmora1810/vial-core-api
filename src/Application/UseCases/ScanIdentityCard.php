<?php

namespace App\Application\UseCases;

use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Services\DocumentExtractorInterface;
use Exception;

class ScanIdentityCard
{
    public function __construct(
        private DocumentExtractorInterface $documentExtractor
    ) {}

    /**
     * Procesa la imagen de la cédula mediante OCR y devuelve los datos patronímicos sugeridos.
     * Permite que el cliente (app móvil o web) muestre los datos para revisión y corrección manual.
     */
    public function execute(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("El archivo del documento de identidad no fue encontrado.", 400);
        }

        $extracted = $this->documentExtractor->extractIdentityCard($filePath);

        return [
            'suggested_identity' => [
                'identification_card' => $extracted->getNationalId()->getValue(),
                'names'               => $extracted->getNames()->getValue(),
                'surnames'            => $extracted->getSurnames()->getValue(),
                'birth_date'          => $extracted->getBirthDate()?->format('Y-m-d'),
                'confidence'          => $extracted->getConfidenceScore(),
            ],
            'file_path'          => $filePath,
            'warnings'           => $extracted->getRawPayload()['warnings'] ?? [],
            'message'            => 'Cédula procesada exitosamente. Por favor verifique y corrija los datos si es necesario antes de confirmar el registro.'
        ];
    }
}

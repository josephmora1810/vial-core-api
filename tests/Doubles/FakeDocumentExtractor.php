<?php

namespace Tests\Doubles;

use App\Domain\Exceptions\DocumentExtractionException;
use App\Domain\Services\DocumentExtractorInterface;
use App\Domain\ValueObjects\BirthDate;
use App\Domain\ValueObjects\ExtractedIdentity;
use App\Domain\ValueObjects\Names;
use App\Domain\ValueObjects\NationalId;
use App\Domain\ValueObjects\Surnames;
use DateTimeImmutable;

/**
 * Extractor falso para pruebas unitarias sin llamadas de red.
 */
class FakeDocumentExtractor implements DocumentExtractorInterface
{
    public function __construct(
        private ?ExtractedIdentity $cannedIdentity = null,
        private bool $shouldFail = false
    ) {}

    public function extractIdentityCard(string $filePath): ExtractedIdentity
    {
        if ($this->shouldFail) {
            throw new DocumentExtractionException("La imagen no es legible o es inválida.", 422);
        }

        return $this->cannedIdentity ?? new ExtractedIdentity(
            nationalId: new NationalId('V80123456'),
            names: new Names('ANDREÍNA SOFÍA'),
            surnames: new Surnames('QUINTERO BASTIDAS'),
            birthDate: new BirthDate('1990-04-17'),
            confidenceScore: 0.98,
            status: 'success',
            rawPayload: ['mocked' => true]
        );
    }

    public function extractVehicleRegistration(string $filePath, ?string $documentType = null): array
    {
        if ($this->shouldFail) {
            throw new DocumentExtractionException("El carnet no es legible.", 422);
        }

        $type = ($documentType === 'vehicle_title' || $documentType === 'niv_certificate') ? 'vehicle_title' : 'vehicle_registration';

        return [
            'document_type' => $type,
            'plate_number'  => $type === 'vehicle_title' ? 'ZZ111WW' : 'AB123CD',
            'brand'         => $type === 'vehicle_title' ? 'Chevrolet' : 'Toyota',
            'model'         => $type === 'vehicle_title' ? 'Aveo' : 'Corolla',
            'year'          => $type === 'vehicle_title' ? 2008 : 2018,
            'color'         => 'Gris',
            'engine_serial' => 'ENG123456',
            'niv'           => $type === 'vehicle_title' ? '0ZZ0Z0000ZZ000000' : '8X123456789012345',
            'vehicle_class' => $type === 'vehicle_title' ? 'Camioneta' : 'Automovil',
            'confidence'    => 0.95,
            'warnings'      => [],
        ];
    }
}

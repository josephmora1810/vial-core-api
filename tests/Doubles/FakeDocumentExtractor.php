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
}

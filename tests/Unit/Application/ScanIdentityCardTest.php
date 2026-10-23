<?php

namespace Tests\Unit\Application;

use App\Application\Auth\UseCases\ScanIdentityCard;
use App\Domain\Auth\Exceptions\DocumentExtractionException;
use App\Domain\Auth\Services\DocumentExtractorInterface;
use App\Domain\Auth\ValueObjects\ExtractedIdentity;
use App\Domain\Users\ValueObjects\{BirthDate, Names, NationalId, Surnames};
use Exception;
use PHPUnit\Framework\TestCase;

class ScanIdentityCardTest extends TestCase
{
    private string $tempFilePath;

    protected function setUp(): void
    {
        $this->tempFilePath = sys_get_temp_dir() . '/test_scan_cedula_' . bin2hex(random_bytes(4)) . '.jpg';
        file_put_contents($this->tempFilePath, 'dummy image content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }
    }

    public function testFailsWhenFileDoesNotExist(): void
    {
        $mockExtractor = $this->createStub(DocumentExtractorInterface::class);
        $useCase = new ScanIdentityCard($mockExtractor);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('El archivo del documento de identidad no fue encontrado.');

        $useCase->execute('/non/existent/path/cedula.jpg');
    }

    public function testPropagatesOcrExtractionException(): void
    {
        $mockExtractor = $this->createMock(DocumentExtractorInterface::class);
        $mockExtractor->expects($this->once())
            ->method('extractIdentityCard')
            ->with($this->tempFilePath)
            ->willThrowException(new DocumentExtractionException('Imagen borrosa o ilegible.', 422));

        $useCase = new ScanIdentityCard($mockExtractor);

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('Imagen borrosa o ilegible.');

        $useCase->execute($this->tempFilePath);
    }

    public function testSuccessfullyExtractsIdentityCard(): void
    {
        $extractedIdentity = new ExtractedIdentity(
            nationalId: new NationalId('V-18765432'),
            names: new Names('Carlos Alberto'),
            surnames: new Surnames('Mendoza Perez'),
            birthDate: new BirthDate('1988-06-15'),
            confidenceScore: 0.96,
            status: 'success',
            rawPayload: [
                'warnings' => ['Borde inferior ligeramente cortado']
            ]
        );

        $mockExtractor = $this->createMock(DocumentExtractorInterface::class);
        $mockExtractor->expects($this->once())
            ->method('extractIdentityCard')
            ->with($this->tempFilePath)
            ->willReturn($extractedIdentity);

        $useCase = new ScanIdentityCard($mockExtractor);
        $result = $useCase->execute($this->tempFilePath);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('suggested_identity', $result);
        $this->assertSame('V-18765432', $result['suggested_identity']['identification_card']);
        $this->assertSame('Carlos Alberto', $result['suggested_identity']['names']);
        $this->assertSame('Mendoza Perez', $result['suggested_identity']['surnames']);
        $this->assertSame('1988-06-15', $result['suggested_identity']['birth_date']);
        $this->assertSame(0.96, $result['suggested_identity']['confidence']);
        $this->assertSame($this->tempFilePath, $result['file_path']);
        $this->assertContains('Borde inferior ligeramente cortado', $result['warnings']);
        $this->assertStringContainsString('Cédula procesada exitosamente', $result['message']);
    }
}

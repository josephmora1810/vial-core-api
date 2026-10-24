<?php

namespace Tests\Unit\Infrastructure;

use App\Domain\Auth\Exceptions\DocumentExtractionException;
use App\Infrastructure\Http\{HttpClientInterface, HttpResponse};
use App\Infrastructure\Services\LaVenezolanaDocumentExtractor;
use Exception;
use PHPUnit\Framework\TestCase;

class LaVenezolanaDocumentExtractorTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LaVenezolanaDocumentExtractor $extractor;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->extractor = new LaVenezolanaDocumentExtractor(
            httpClient: $this->httpClient,
            baseUrl: 'https://ocr.example.com',
            apiKey: 'test-ocr-api-key'
        );
    }

    public function testExtractIdentityCardConnectionFailureThrows503(): void
    {
        $this->httpClient
            ->method('postMultipart')
            ->willThrowException(new Exception('Connection timeout'));

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionCode(503);

        $this->extractor->extractIdentityCard('dummy_path.jpg');
    }

    public function testExtractIdentityCardHttpErrorThrows422(): void
    {
        $response = new HttpResponse(
            status: 422,
            headers: [],
            body: json_encode(['detail' => 'Imagen borrosa no apta para OCR.'])
        );

        $this->httpClient
            ->method('postMultipart')
            ->willReturn($response);

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionCode(422);
        $this->expectExceptionMessage('Imagen borrosa no apta para OCR.');

        $this->extractor->extractIdentityCard('dummy_path.jpg');
    }

    public function testExtractIdentityCardMissingRequiredFieldsThrows422(): void
    {
        $response = new HttpResponse(
            status: 200,
            headers: [],
            body: json_encode([
                'status' => 'success',
                'fields' => [
                    'document_number' => ['value' => 'V-12345678'],
                    // given_names missing
                ]
            ])
        );

        $this->httpClient
            ->method('postMultipart')
            ->willReturn($response);

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionCode(422);

        $this->extractor->extractIdentityCard('dummy_path.jpg');
    }

    public function testExtractIdentityCardSuccess(): void
    {
        $response = new HttpResponse(
            status: 200,
            headers: [],
            body: json_encode([
                'status' => 'success',
                'fields' => [
                    'document_number' => ['value' => 'V-19876543', 'confidence' => 0.98],
                    'given_names'     => ['value' => 'ANA MARÍA'],
                    'surnames'        => ['value' => 'FERNÁNDEZ PÉREZ'],
                    'date_of_birth'   => ['value' => '1992-07-20'],
                ]
            ])
        );

        $this->httpClient
            ->method('postMultipart')
            ->willReturn($response);

        $identity = $this->extractor->extractIdentityCard('dummy_path.jpg');

        $this->assertSame('V-19876543', $identity->getNationalId()->getValue());
        $this->assertSame('ANA MARÍA', $identity->getNames()->getValue());
        $this->assertSame('FERNÁNDEZ PÉREZ', $identity->getSurnames()->getValue());
        $this->assertSame('1992-07-20', $identity->getBirthDate()?->format('Y-m-d'));
        $this->assertEquals(0.98, $identity->getConfidenceScore());
        $this->assertSame('success', $identity->getStatus());
    }

    public function testExtractVehicleRegistrationConnectionFailureThrows503(): void
    {
        $this->httpClient
            ->method('postMultipart')
            ->willThrowException(new Exception('Network error'));

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionCode(503);

        $this->extractor->extractVehicleRegistration('dummy_path.jpg', 'vehicle_registration');
    }

    public function testExtractVehicleRegistrationHttpErrorThrows422(): void
    {
        $response = new HttpResponse(
            status: 422,
            headers: [],
            body: json_encode(['detail' => 'Documento ilegible'])
        );

        $this->httpClient
            ->method('postMultipart')
            ->willReturn($response);

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionCode(422);

        $this->extractor->extractVehicleRegistration('dummy_path.jpg');
    }

    public function testExtractVehicleRegistrationSuccess(): void
    {
        $response = new HttpResponse(
            status: 200,
            headers: [],
            body: json_encode([
                'status'        => 'success',
                'document_type' => 'vehicle_registration',
                'fields'        => [
                    'license_plate' => ['value' => 'AF123JK', 'confidence' => 0.95],
                    'make'          => ['value' => 'TOYOTA'],
                    'model'         => ['value' => 'COROLLA'],
                    'year'          => ['value' => '2015'],
                    'color'         => ['value' => 'BLANCO'],
                    'engine_serial' => ['value' => '1ZZ1234567'],
                    'vin'           => ['value' => '2T1BR32E55C123456'],
                    'vehicle_type'  => ['value' => 'AUTOMOVIL'],
                    'owner_id'      => ['value' => 'V-12345678'],
                    'owner_name'    => ['value' => 'CARLOS GOMEZ'],
                ],
                'warnings'      => [],
            ])
        );

        $this->httpClient
            ->method('postMultipart')
            ->willReturn($response);

        $reg = $this->extractor->extractVehicleRegistration('dummy_path.jpg', 'vehicle_registration');

        $this->assertSame('vehicle_registration', $reg->getDocumentType());
        $this->assertSame('AF123JK', $reg->getPlateNumber());
        $this->assertSame('TOYOTA', $reg->getBrand());
        $this->assertSame('COROLLA', $reg->getModel());
        $this->assertSame(2015, $reg->getYear());
        $this->assertSame('BLANCO', $reg->getColor());
        $this->assertSame('1ZZ1234567', $reg->getEngineSerial());
        $this->assertSame('2T1BR32E55C123456', $reg->getNiv());
        $this->assertSame('AUTOMOVIL', $reg->getVehicleClass());
        $this->assertSame('V-12345678', $reg->getOwnerNationalId());
        $this->assertSame('CARLOS GOMEZ', $reg->getOwnerName());
        $this->assertEquals(0.95, $reg->getConfidence());
    }

    public function testExtractVehicleRegistrationTitleNivMapping(): void
    {
        $response = new HttpResponse(
            status: 200,
            headers: [],
            body: json_encode([
                'status'        => 'success',
                'document_type' => 'vehicle_title',
                'fields'        => [
                    'vin' => ['value' => 'NIV987654321', 'confidence' => 0.99],
                ]
            ])
        );

        $this->httpClient
            ->method('postMultipart')
            ->willReturn($response);

        $reg = $this->extractor->extractVehicleRegistration('dummy_path.jpg', 'niv_certificate');
        $this->assertSame('vehicle_title', $reg->getDocumentType());
        $this->assertSame('NIV987654321', $reg->getNiv());
    }
}

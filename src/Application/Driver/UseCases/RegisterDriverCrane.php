<?php

namespace App\Application\Driver\UseCases;

use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\{DocumentStatus, DocumentType};
use App\Domain\Auth\Exceptions\DocumentExtractionException;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Auth\Services\DocumentExtractorInterface;
use App\Domain\Users\Enums\UserRole;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\{VehicleStatus, VehicleType};
use App\Domain\Vehicles\Repositories\{VehicleCatalogRepositoryInterface, VehicleRepositoryInterface};
use App\Domain\Vehicles\ValueObjects\{EngineSerial, Niv, PlateNumber};
use Exception;

class RegisterDriverCrane
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
        private UserRepositoryInterface $userRepository,
        private DocumentRepositoryInterface $documentRepository,
        private DocumentExtractorInterface $documentExtractor,
        private ?VehicleCatalogRepositoryInterface $catalogRepository = null,
        private ?EnqueueDocumentS3Upload $enqueueS3 = null
    ) {}

    /**
     * Registra una unidad de grúa (CRANE) para un operador (Gruero) cumpliendo con el FR-09.
     * Procesa el Certificado de Registro del INTT / Carnet mediante OCR, persiste la evidencia y
     * encola la subida asíncrona a Amazon S3. Opcionalmente recibe la Licencia de Conducir del chofer.
     *
     * @param int $driverUserId
     * @param string|null $rawPlate
     * @param string|null $rawNiv
     * @param int|null $rawYear
     * @param string|null $rawColor
     * @param string|null $rawEngineSerial
     * @param string|null $rawBrand
     * @param string|null $rawModel
     * @param int|null $brandId
     * @param int|null $modelId
     * @param string|null $carnetFilePath  Ruta del archivo de carnet / certificado de grúa
     * @param string|null $carnetDocType   'vehicle_registration' o 'vehicle_title'
     * @param string|null $licenseFilePath Ruta del archivo de licencia de conducir (opcional)
     * @param string|null $licenseGrade    Grado de la licencia (ej: '5to Grado')
     * @return array<string, mixed>
     */
    public function execute(
        int $driverUserId,
        ?string $rawPlate = null,
        ?string $rawNiv = null,
        ?int $rawYear = null,
        ?string $rawColor = null,
        ?string $rawEngineSerial = null,
        ?string $rawBrand = null,
        ?string $rawModel = null,
        ?int $brandId = null,
        ?int $modelId = null,
        ?string $carnetFilePath = null,
        ?string $carnetDocType = 'vehicle_registration',
        ?string $licenseFilePath = null,
        ?string $licenseGrade = null
    ): array {
        // 1. Validar que el usuario exista y tenga el rol de Gruero (driver)
        $driver = $this->userRepository->findById($driverUserId);
        if ($driver === null) {
            throw new Exception('Operador de grúa no encontrado.', 404);
        }

        if (!$driver->hasRole(UserRole::Driver->getValue()) && !$driver->hasRole(UserRole::Admin->getValue())) {
            throw new Exception('El usuario no posee el rol de conductor u operador de grúas.', 403);
        }

        // 2. Extracción OCR del documento de la grúa (Carnet de Circulación o Título INTT)
        $hasCarnetFile = !empty($carnetFilePath) && file_exists($carnetFilePath);
        $extractedCarnet = null;
        if ($hasCarnetFile) {
            try {
                $extractedCarnet = $this->documentExtractor->extractVehicleRegistration(
                    $carnetFilePath,
                    $carnetDocType ?? 'vehicle_registration'
                );
            } catch (DocumentExtractionException) {
                // Si el OCR falla por nitidez, se permite continuar si se enviaron datos manuales
                $extractedCarnet = null;
            }
        }

        // 3. Resolver campos prioritarios (formulario manual o lectura de OCR)
        $carnetData = $extractedCarnet instanceof \App\Domain\Auth\ValueObjects\ExtractedVehicleRegistration 
            ? $extractedCarnet->toArray() 
            : (is_array($extractedCarnet) ? $extractedCarnet : []);

        $finalPlate = !empty($rawPlate) ? trim($rawPlate) : ($carnetData['plate_number'] ?? null);
        $finalNiv = !empty($rawNiv) ? trim($rawNiv) : ($carnetData['niv'] ?? null);
        $finalYear = !empty($rawYear) ? (int)$rawYear : ($carnetData['year'] ?? null);
        $finalColor = !empty($rawColor) ? trim($rawColor) : ($carnetData['color'] ?? 'BLANCO');
        $finalEngine = !empty($rawEngineSerial) ? trim($rawEngineSerial) : ($carnetData['engine_serial'] ?? null);
        $finalBrandName = !empty($rawBrand) ? trim($rawBrand) : ($carnetData['brand'] ?? 'CHEVROLET');
        $finalModelName = !empty($rawModel) ? trim($rawModel) : ($carnetData['model'] ?? 'F-350 GRUA');

        if (empty($finalPlate) || empty($finalNiv) || empty($finalYear)) {
            throw new Exception(
                'Debe adjuntar la imagen del Carnet/Título de la grúa o suministrar Placa, NIV y Año.',
                422
            );
        }

        $plate = new PlateNumber($finalPlate);
        $niv = new Niv($finalNiv);
        $engineSerial = !empty($finalEngine) ? new EngineSerial($finalEngine) : null;

        // 4. Validar unicidad de placa y NIV en MariaDB
        if ($this->vehicleRepository->findByPlateNumber($plate) !== null) {
            throw new Exception("Ya existe una grúa o vehículo registrado con la placa {$plate->getValue()}.", 409);
        }

        if ($this->vehicleRepository->findByNiv($niv) !== null) {
            throw new Exception("Ya existe una unidad registrada con el NIV/Serial de Carrocería {$niv->getValue()}.", 409);
        }

        // 5. Resolver model_id oficial de la grúa en el catálogo
        $resolvedModelId = $modelId;
        if ($resolvedModelId === null && $this->catalogRepository !== null) {
            $brand = $this->catalogRepository->findBrandByName($finalBrandName);
            if ($brand !== null) {
                $m = $this->catalogRepository->findModelByNameAndBrand($finalModelName, (int)$brand['id']);
                if ($m !== null) {
                    $resolvedModelId = (int)$m['id'];
                }
            }
        }
        $resolvedModelId ??= 839; // Fallback a modelo oficial de grúa (GRUA DE PLATAFORMA)

        // Crear la entidad Vehicle 3NF con tipo CRANE y estado PENDING (requiere revisión administrativa)
        $crane = Vehicle::create(
            userId: $driverUserId,
            modelId: $resolvedModelId,
            plateNumber: $plate,
            niv: $niv,
            engineSerial: $engineSerial,
            year: $finalYear,
            color: $finalColor,
            isVerified: false,
            status: VehicleStatus::Pending,
            type: VehicleType::Crane,
            brandName: $finalBrandName,
            modelName: $finalModelName
        );

        $this->vehicleRepository->save($crane);
        $craneId = (int)$crane->getId();

        // 6. Persistir el documento del Carnet de Circulación / Título de la grúa
        $carnetDocId = null;
        if ($hasCarnetFile) {
            $isTitle = in_array(strtolower(trim((string)$carnetDocType)), ['vehicle_title', 'niv_certificate', 'niv', 'title'], true);
            $docType = $isTitle ? DocumentType::NivCertificate : DocumentType::VehicleRegistration;

            $carnetDoc = Document::create(
                documentableType: 'vehicle',
                documentableId: $craneId,
                documentType: $docType,
                filePath: $carnetFilePath,
                extractedData: [
                    'plate_number'  => $plate->getValue(),
                    'niv'           => $niv->getValue(),
                    'engine_serial' => $engineSerial?->getValue(),
                    'year'          => $finalYear,
                    'color'         => $finalColor,
                    'type'          => 'CRANE',
                    'ocr_extracted' => $extractedCarnet !== null,
                ],
                status: DocumentStatus::Pending
            );
            $this->documentRepository->save($carnetDoc);
            $carnetDocId = $carnetDoc->getId();

            // Encolar a AWS S3
            if ($this->enqueueS3 !== null && $carnetDocId !== null) {
                $this->enqueueS3->execute(
                    documentId: $carnetDocId,
                    localFilePath: $carnetFilePath,
                    documentType: $carnetDoc->getDocumentType()->getValue(),
                    documentableType: 'vehicle',
                    documentableId: $craneId
                );
            }
        }

        // 7. Si se adjuntó la Licencia de Conducir del chofer en la misma petición
        $licenseDocId = null;
        if (!empty($licenseFilePath) && file_exists($licenseFilePath)) {
            $existingLicense = $this->documentRepository->findLatestByEntity(
                'user',
                $driverUserId,
                DocumentType::DrivingLicense
            );

            if ($existingLicense !== null) {
                $existingLicense->resubmit($licenseFilePath, [
                    'grade' => $licenseGrade ?? '5TO GRADO',
                    'role'  => 'driver',
                ]);
                $this->documentRepository->save($existingLicense);
                $licenseDoc = $existingLicense;
            } else {
                $licenseDoc = Document::create(
                    documentableType: 'user',
                    documentableId: $driverUserId,
                    documentType: DocumentType::DrivingLicense,
                    filePath: $licenseFilePath,
                    extractedData: [
                        'grade' => $licenseGrade ?? '5TO GRADO',
                        'role'  => 'driver',
                    ],
                    status: DocumentStatus::Pending
                );
                $this->documentRepository->save($licenseDoc);
            }
            $licenseDocId = $licenseDoc->getId();

            // Encolar a AWS S3
            if ($this->enqueueS3 !== null && $licenseDocId !== null) {
                $this->enqueueS3->execute(
                    documentId: $licenseDocId,
                    localFilePath: $licenseFilePath,
                    documentType: DocumentType::DrivingLicense->getValue(),
                    documentableType: 'user',
                    documentableId: $driverUserId
                );
            }
        }

        return [
            'crane'                  => VehicleResponseDto::fromEntity($crane)->toArray(),
            'carnet_document_id'     => $carnetDocId,
            'license_document_id'    => $licenseDocId,
            'is_fleet_ready'         => false,
            'status_message'         => 'Grúa registrada con éxito. La unidad y sus documentos se encuentran en revisión por el Administrador antes de habilitar el despacho de rescates.'
        ];
    }
}

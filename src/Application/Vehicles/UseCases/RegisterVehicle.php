<?php

namespace App\Application\Vehicles\UseCases;

use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Domain\Auth\Entities\Document;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Vehicles\Enums\VehicleStatus;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleCatalogRepositoryInterface;
use App\Domain\Vehicles\Repositories\VehicleRepositoryInterface;
use App\Domain\Vehicles\ValueObjects\EngineSerial;
use App\Domain\Vehicles\ValueObjects\Niv;
use App\Domain\Vehicles\ValueObjects\PlateNumber;
use Exception;

class RegisterVehicle
{
    public function __construct(
        private VehicleRepositoryInterface $vehicleRepository,
        private UserRepositoryInterface $userRepository,
        private ?DocumentRepositoryInterface $documentRepository = null,
        private ?VehicleCatalogRepositoryInterface $catalogRepository = null,
        private ?EnqueueDocumentS3Upload $enqueueS3 = null
    ) {}

    public function execute(
        int $userId,
        string $rawPlate,
        string $rawNiv,
        int $year,
        string $color,
        ?string $rawEngineSerial = null,
        ?int $brandId = null,
        ?int $modelId = null,
        ?int $vehicleClassId = null,
        ?int $vehicleSubclassId = null,
        ?string $brandName = null,
        ?string $modelName = null,
        ?string $carnetFilePath = null,
        ?string $documentType = null,
        bool $autoVerifyBySeller = false
    ): array {
        // 1. Validar que el usuario exista
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        // 2. Validar Value Objects de Dominio (Placa, NIV y Serial de Motor)
        $plate = new PlateNumber($rawPlate);
        $niv = new Niv($rawNiv);
        $engineSerial = !empty($rawEngineSerial) ? new EngineSerial($rawEngineSerial) : null;

        // 3. Comprobar unicidad de Placa en el sistema
        $existingByPlate = $this->vehicleRepository->findByPlateNumber($plate);
        if ($existingByPlate !== null) {
            throw new Exception("Ya existe un vehículo registrado con la placa {$plate->getValue()}.", 409);
        }

        // 4. Comprobar unicidad de NIV en el sistema
        $existingByNiv = $this->vehicleRepository->findByNiv($niv);
        if ($existingByNiv !== null) {
            throw new Exception("Ya existe un vehículo registrado con el N.I.V {$niv->getValue()}.", 409);
        }

        // 5. Validar año del vehículo
        $currentYear = (int)date('Y') + 1;
        if ($year < 1900 || $year > $currentYear) {
            throw new Exception("El año del vehículo ({$year}) no es válido.", 400);
        }

        // 6. Crear la Entidad de Dominio Vehicle
        // Si fue registrado por una vendedora, ya fue revisado y se aprueba de inmediato
        $hasDocument = !empty($carnetFilePath) && file_exists($carnetFilePath);
        $status = $autoVerifyBySeller ? VehicleStatus::Active : VehicleStatus::Pending;

        $vehicle = Vehicle::create(
            userId: $userId,
            plateNumber: $plate,
            niv: $niv,
            engineSerial: $engineSerial,
            year: $year,
            color: $color,
            vehicleClassId: $vehicleClassId,
            vehicleSubclassId: $vehicleSubclassId,
            brandId: $brandId,
            modelId: $modelId,
            brandName: $brandName,
            modelName: $modelName,
            isVerified: $autoVerifyBySeller,
            status: $status
        );

        // 7. Persistir el vehículo
        $this->vehicleRepository->save($vehicle);

        // 8. Si se adjuntó el documento (carnet o título/NIV), persistirlo en la tabla polimórfica documents
        if ($hasDocument && $this->documentRepository !== null && $vehicle->getId() !== null) {
            $isTitle = in_array(strtolower(trim((string)$documentType)), ['vehicle_title', 'niv_certificate', 'niv', 'title'], true);
            $docType = $isTitle ? DocumentType::NivCertificate : DocumentType::VehicleRegistration;

            $doc = Document::create(
                documentableType: 'vehicle',
                documentableId: (int)$vehicle->getId(),
                documentType: $docType,
                filePath: (string)$carnetFilePath,
                extractedData: [
                    'plate_number'        => $plate->getValue(),
                    'niv'                 => $niv->getValue(),
                    'engine_serial'       => $engineSerial?->getValue(),
                    'year'                => $year,
                    'color'               => $color,
                    'verified_by_seller'  => $autoVerifyBySeller,
                ],
                status: $autoVerifyBySeller ? DocumentStatus::Verified : DocumentStatus::Pending
            );
            $this->documentRepository->save($doc);

            // Encolar subida asíncrona a Amazon S3 (NFR-20-B)
            if ($this->enqueueS3 !== null && $doc->getId() !== null && file_exists((string)$carnetFilePath)) {
                $this->enqueueS3->execute(
                    documentId: $doc->getId(),
                    localFilePath: (string)$carnetFilePath,
                    documentType: $doc->getDocumentType()->getValue(),
                    documentableType: $doc->getDocumentableType(),
                    documentableId: $doc->getDocumentableId()
                );
            }
        }

        return VehicleResponseDto::fromEntity(
            $vehicle,
            resolvedBrandName: $brandName,
            resolvedModelName: $modelName
        )->toArray();
    }
}

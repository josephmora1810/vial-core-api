<?php

namespace App\Application\Vehicles\UseCases;

use App\Application\Documents\UseCases\EnqueueDocumentS3Upload;
use App\Application\Vehicles\DTOs\VehicleDetailedResponseDto;
use App\Application\Vehicles\DTOs\VehicleResponseDto;
use App\Domain\Auth\Entities\Document;
use App\Domain\Auth\Enums\DocumentStatus;
use App\Domain\Auth\Enums\DocumentType;
use App\Domain\Auth\Repositories\DocumentRepositoryInterface;
use App\Domain\Users\Repositories\UserRepositoryInterface;
use App\Domain\Vehicles\Entities\Vehicle;
use App\Domain\Vehicles\Enums\VehicleStatus;
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
        ?string $rawPlate = null,
        ?string $rawNiv = null,
        int $year = 2000,
        string $color = 'Blanco',
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
        // 1. Validar existencia del usuario
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        // 2. Validar que exista al menos Placa (Carnet) o NIV (Título/Certificado)
        $cleanPlate = !empty($rawPlate) ? trim($rawPlate) : null;
        $cleanNiv = !empty($rawNiv) ? trim($rawNiv) : null;

        if ($cleanPlate === null && $cleanNiv === null) {
            throw new Exception("Debe suministrar al menos la Placa o el N.I.V. del vehículo.", 422);
        }

        // 3. Validar y verificar unicidad de Placa (si fue provista)
        $plate = null;
        if ($cleanPlate !== null) {
            $plate = new PlateNumber($cleanPlate);
            $existingByPlate = $this->vehicleRepository->findByPlateNumber($plate);
            if ($existingByPlate !== null) {
                throw new Exception("Ya existe un vehículo registrado con la placa {$plate->getValue()}.", 409);
            }
        }

        // 4. Validar y verificar unicidad de NIV (si fue provisto)
        $niv = null;
        if ($cleanNiv !== null) {
            $niv = new Niv($cleanNiv);
            $existingByNiv = $this->vehicleRepository->findByNiv($niv);
            if ($existingByNiv !== null) {
                throw new Exception("Ya existe un vehículo registrado con el N.I.V {$niv->getValue()}.", 409);
            }
        }

        // 5. Validar serial de motor (opcional)
        $engineSerial = !empty($rawEngineSerial) ? new EngineSerial($rawEngineSerial) : null;

        // 6. Validar año de fabricación
        $currentYear = (int)date('Y') + 1;
        if ($year < 1900 || $year > $currentYear) {
            throw new Exception("El año del vehículo ({$year}) no es válido.", 400);
        }

        // 7. Resolución estricta del Modelo en el catálogo oficial (3NF)
        // NUNCA auto-crea marcas o modelos erróneos en caliente (ej: 'Berr').
        $resolvedModelId = $modelId;
        $resolvedBrandName = $brandName;
        $resolvedModelName = $modelName;

        if ($this->catalogRepository !== null) {
            if ($resolvedModelId === null && !empty($brandName) && !empty($modelName)) {
                $matchedBrand = $this->catalogRepository->findBrandByName($brandName);
                if ($matchedBrand !== null) {
                    $brandId = (int)$matchedBrand['id'];
                    $resolvedBrandName = $matchedBrand['name'];
                    $matchedModel = $this->catalogRepository->findModelByNameAndBrand($modelName, $brandId);
                    if ($matchedModel !== null) {
                        $resolvedModelId = (int)$matchedModel['id'];
                        $resolvedModelName = $matchedModel['name'];
                    }
                }
            } elseif ($resolvedModelId !== null && $brandId !== null && empty($resolvedModelName)) {
                $models = $this->catalogRepository->getModelsByBrand($brandId);
                foreach ($models as $m) {
                    if ((int)$m['id'] === $resolvedModelId) {
                        $resolvedModelName = $m['name'];
                        break;
                    }
                }
            }
        }

        // Si después de la búsqueda no hay un model_id válido, rechazar con 422
        // para que la app abra el formulario de búsqueda/selección del catálogo
        if ($resolvedModelId === null) {
            $resolvedModelId = 1; // Fallback seguro de desarrollo solo si no hay catálogo inyectado
            if ($this->catalogRepository !== null) {
                throw new Exception(
                    "El modelo de vehículo no fue encontrado en el catálogo oficial. Por favor seleccione la marca y modelo correspondientes desde la lista.",
                    422
                );
            }
        }

        // 8. Crear la Entidad de Dominio Vehicle (3NF)
        $status = $autoVerifyBySeller ? VehicleStatus::Active : VehicleStatus::Pending;

        $vehicle = Vehicle::create(
            userId: $userId,
            modelId: $resolvedModelId,
            plateNumber: $plate,
            niv: $niv,
            engineSerial: $engineSerial,
            year: $year,
            color: $color,
            isVerified: $autoVerifyBySeller,
            status: $status,
            brandName: $resolvedBrandName,
            modelName: $resolvedModelName
        );

        $this->vehicleRepository->save($vehicle);

        // 9. Si se adjuntó el documento (carnet o título/NIV), persistirlo en documents
        $hasDocument = !empty($carnetFilePath) && file_exists($carnetFilePath);
        if ($hasDocument && $this->documentRepository !== null && $vehicle->getId() !== null) {
            $isTitle = in_array(strtolower(trim((string)$documentType)), ['vehicle_title', 'niv_certificate', 'niv', 'title'], true);
            $docType = $isTitle ? DocumentType::NivCertificate : DocumentType::VehicleRegistration;

            $doc = Document::create(
                documentableType: 'vehicle',
                documentableId: (int)$vehicle->getId(),
                documentType: $docType,
                filePath: (string)$carnetFilePath,
                extractedData: [
                    'plate_number'        => $plate?->getValue(),
                    'niv'                 => $niv?->getValue(),
                    'engine_serial'       => $engineSerial?->getValue(),
                    'year'                => $year,
                    'color'               => $color,
                    'model_id'            => $resolvedModelId,
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

        // 10. Devolver la respuesta detallada consultando la vista 3NF
        $detailed = $this->vehicleRepository->findDetailedByUuid($vehicle->getUuid());
        if ($detailed !== null) {
            return VehicleDetailedResponseDto::fromViewRow($detailed)->toArray();
        }

        return VehicleResponseDto::fromEntity(
            $vehicle,
            resolvedBrandName: $resolvedBrandName,
            resolvedModelName: $resolvedModelName
        )->toArray();
    }
}

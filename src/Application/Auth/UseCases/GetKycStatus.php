<?php

namespace App\Application\Auth\UseCases;

use App\Domain\Auth\{
    Enums\DocumentType,
    Repositories\DocumentRepositoryInterface
};
use App\Domain\Users\Repositories\UserRepositoryInterface;
use Exception;

class GetKycStatus
{
    public function __construct(
        private DocumentRepositoryInterface $documentRepository,
        private UserRepositoryInterface $userRepository
    ) {}

    public function execute(int $userId): array
    {
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new Exception("Usuario no encontrado.", 404);
        }

        $userDocs = $this->documentRepository->findAllByEntity('user', $userId);

        $idDoc = null;
        $passportDoc = null;
        $licenseDoc = null;

        foreach ($userDocs as $doc) {
            if ($doc->getDocumentType() === DocumentType::IdentityCard && $idDoc === null) {
                $idDoc = $doc;
            } elseif ($doc->getDocumentType() === DocumentType::Passport && $passportDoc === null) {
                $passportDoc = $doc;
            } elseif ($doc->getDocumentType() === DocumentType::DrivingLicense && $licenseDoc === null) {
                $licenseDoc = $doc;
            }
        }

        $isIdentityVerified = $this->documentRepository->isUserIdentityVerified($userId);
        $hasVerifiedVehicle = $this->documentRepository->hasVerifiedVehicleDocument($userId);

        return [
            'user_id'              => $userId,
            'is_identity_verified' => $isIdentityVerified,
            'has_verified_vehicle' => $hasVerifiedVehicle,
            'is_fully_kyc_passed'  => $isIdentityVerified && $hasVerifiedVehicle,
            'documents'            => [
                'identity_card'   => $idDoc ? [
                    'status'    => $idDoc->getStatus()->getValue(),
                    'file_path' => $idDoc->getFilePath(),
                ] : null,
                'passport'        => $passportDoc ? [
                    'status'    => $passportDoc->getStatus()->getValue(),
                    'file_path' => $passportDoc->getFilePath(),
                ] : null,
                'driving_license' => $licenseDoc ? [
                    'status'    => $licenseDoc->getStatus()->getValue(),
                    'file_path' => $licenseDoc->getFilePath(),
                ] : null,
            ]
        ];
    }
}

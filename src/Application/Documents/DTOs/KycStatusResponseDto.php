<?php

namespace App\Application\Documents\DTOs;

readonly class KycStatusResponseDto
{
    /**
     * @param array<string, array{status: string, file_path: string}|null> $documents
     */
    public function __construct(
        public int $user_id,
        public bool $is_identity_verified,
        public bool $has_verified_vehicle,
        public bool $is_fully_kyc_passed,
        public array $documents
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $docs = is_array($data['documents'] ?? null) ? $data['documents'] : [];
        return new self(
            user_id: (int)($data['user_id'] ?? 0),
            is_identity_verified: (bool)($data['is_identity_verified'] ?? false),
            has_verified_vehicle: (bool)($data['has_verified_vehicle'] ?? false),
            is_fully_kyc_passed: (bool)($data['is_fully_kyc_passed'] ?? false),
            documents: [
                'identity_card'   => $docs['identity_card'] ?? null,
                'passport'        => $docs['passport'] ?? null,
                'driving_license' => $docs['driving_license'] ?? null,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'              => $this->user_id,
            'is_identity_verified' => $this->is_identity_verified,
            'has_verified_vehicle' => $this->has_verified_vehicle,
            'is_fully_kyc_passed'  => $this->is_fully_kyc_passed,
            'documents'            => $this->documents,
        ];
    }
}

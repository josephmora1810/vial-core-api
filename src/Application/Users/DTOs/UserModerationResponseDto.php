<?php

declare(strict_types=1);

namespace App\Application\Users\DTOs;

readonly class UserModerationResponseDto
{
    public function __construct(
        public int $user_id,
        public string $status,
        public ?string $reason = null,
        public ?int $admin_user_id = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            user_id: (int)($data['user_id'] ?? 0),
            status: (string)($data['status'] ?? ''),
            reason: isset($data['reason']) ? (string)$data['reason'] : null,
            admin_user_id: isset($data['admin_user_id']) ? (int)$data['admin_user_id'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $res = [
            'user_id' => $this->user_id,
            'status'  => $this->status,
        ];
        if ($this->reason !== null) {
            $res['reason'] = $this->reason;
        }
        if ($this->admin_user_id !== null) {
            $res['admin_user_id'] = $this->admin_user_id;
        }
        return $res;
    }
}

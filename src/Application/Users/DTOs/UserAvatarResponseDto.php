<?php

declare(strict_types=1);

namespace App\Application\Users\DTOs;

readonly class UserAvatarResponseDto
{
    public function __construct(
        public string $url,
        public ?string $initials = null,
        public ?int $image_id = null
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: (string)($data['url'] ?? ''),
            initials: isset($data['initials']) ? (string)$data['initials'] : null,
            image_id: isset($data['image_id']) ? (int)$data['image_id'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $res = ['url' => $this->url];
        if ($this->initials !== null) {
            $res['initials'] = $this->initials;
        }
        if ($this->image_id !== null) {
            $res['image_id'] = $this->image_id;
        }
        return $res;
    }
}

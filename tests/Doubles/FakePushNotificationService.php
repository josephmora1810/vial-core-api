<?php

namespace Tests\Doubles;

use App\Domain\Shared\Services\PushNotificationInterface;

class FakePushNotificationService implements PushNotificationInterface
{
    /** @var list<array{interests: array<string>, title: string, body: string, data: array<string, mixed>}> */
    public array $sentToInterests = [];

    /** @var list<array{users: array<string>, title: string, body: string, data: array<string, mixed>}> */
    public array $sentToUsers = [];

    public function publishToInterests(array $interests, string $title, string $body, array $data = []): bool
    {
        $this->sentToInterests[] = [
            'interests' => $interests,
            'title'     => $title,
            'body'      => $body,
            'data'      => $data,
        ];
        return true;
    }

    public function publishToUsers(array $userIds, string $title, string $body, array $data = []): bool
    {
        $this->sentToUsers[] = [
            'users' => $userIds,
            'title' => $title,
            'body'  => $body,
            'data'  => $data,
        ];
        return true;
    }
}

<?php

namespace Tests\Doubles;

use App\Domain\Shared\Services\MailerInterface;

class InMemoryMailer implements MailerInterface
{
    /** @var list<array{to: string, subject: string, htmlBody: string}> */
    private array $sentMails = [];

    public function send(string $to, string $subject, string $htmlBody): bool
    {
        $this->sentMails[] = [
            'to'       => $to,
            'subject'  => $subject,
            'htmlBody' => $htmlBody,
        ];

        return true;
    }

    public function getSentCount(): int
    {
        return count($this->sentMails);
    }

    public function getLastSent(): ?array
    {
        return $this->sentMails[count($this->sentMails) - 1] ?? null;
    }

    public function clear(): void
    {
        $this->sentMails = [];
    }
}

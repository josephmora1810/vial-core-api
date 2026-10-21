<?php

namespace App\Domain\Shared\Services;

interface MailerInterface
{
    public function send(string $to, string $subject, string $htmlBody): bool;
}
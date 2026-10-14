<?php

namespace App\Domain\Services;

interface MailerInterface
{
    public function send(string $to, string $subject, string $htmlBody): bool;
}
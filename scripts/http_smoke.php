<?php
require_once __DIR__ . '/../autoload.php';

use App\Infrastructure\Http\CurlHttpClient;

$client = new CurlHttpClient();

// 1. GET simple
$health = $client->get('http://localhost:8080/api/v1/health');
echo "Health: {$health->status}\n";
print_r($health->json());

// 2. Multipart con una foto REAL (el OCR verifica los bytes mágicos aunque esté en modo fake)
$result = $client->postMultipart(
    'http://localhost:8080/api/v1/extractions',
    ['document_type' => 'identity_card'],
    ['document' => __DIR__ . '/image.jpg']
);
echo "\nExtraction: {$result->status}\n";
print_r($result->json());
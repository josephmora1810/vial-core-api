<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    protected function setUp(): void
    {
        Response::$shouldExit = false;
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
    }

    public function testJsonResponseWithArray(): void
    {
        ob_start();
        try {
            Response::json(['foo' => 'bar'], 200, 'success', 'Operación exitosa');
        } finally {
            $output = (string)ob_get_clean();
        }

        $decoded = json_decode($output, true);
        $this->assertSame(200, $decoded['code']);
        $this->assertSame('success', $decoded['status']);
        $this->assertSame('Operación exitosa', $decoded['message']);
        $this->assertSame(['foo' => 'bar'], $decoded['data']);
    }

    public function testJsonResponseWithObjectHavingToArray(): void
    {
        $obj = new class {
            public function toArray(): array {
                return ['key' => 'val'];
            }
        };

        ob_start();
        try {
            Response::json($obj, 201, 'success', 'Creado');
        } finally {
            $output = (string)ob_get_clean();
        }

        $decoded = json_decode($output, true);
        $this->assertSame(201, $decoded['code']);
        $this->assertSame(['key' => 'val'], $decoded['data']);
    }

    public function testRawJsonResponse(): void
    {
        ob_start();
        try {
            Response::rawJson(['raw_key' => 'raw_val'], 200);
        } finally {
            $output = (string)ob_get_clean();
        }

        $decoded = json_decode($output, true);
        $this->assertSame(['raw_key' => 'raw_val'], $decoded);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Core\Request;
use App\Infrastructure\Security\UploadedFileSecurity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class UploadedFileSecurityTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sec_test_' . bin2hex(random_bytes(4));
        @mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
    }

    public function testRejectsDisallowedExtension(): void
    {
        $fakePhp = $this->tempDir . '/shell.php';
        file_put_contents($fakePhp, '<?php phpinfo();');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no permitida');

        UploadedFileSecurity::validateAndStore([
            'tmp_name' => $fakePhp,
            'name'     => 'shell.php',
            'error'    => UPLOAD_ERR_OK,
        ]);
    }

    public function testRejectsSpoofedMimeType(): void
    {
        // Archivo que tiene extensión .jpg pero contenido real es script PHP
        $spoofed = $this->tempDir . '/fake.jpg';
        file_put_contents($spoofed, '<?php echo "malicious"; ?>');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no es permitido');

        UploadedFileSecurity::validateAndStore([
            'tmp_name' => $spoofed,
            'name'     => 'fake.jpg',
            'error'    => UPLOAD_ERR_OK,
        ]);
    }

    public function testAcceptsValidPngImage(): void
    {
        // 1x1 transparent PNG magic bytes
        $validPng = $this->tempDir . '/valid.png';
        file_put_contents($validPng, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));

        $storedPath = UploadedFileSecurity::validateAndStore([
            'tmp_name' => $validPng,
            'name'     => 'test.png',
            'error'    => UPLOAD_ERR_OK,
        ], 'test_doc');

        $this->assertFileExists($storedPath);
        $this->assertStringContainsString('test_doc_', basename($storedPath));
        $this->assertStringEndsWith('.png', $storedPath);

        @unlink($storedPath);
    }

    public function testResolveRequestFileRejectsPathTraversalInDev(): void
    {
        $_ENV['APP_ENV'] = 'local';
        $_POST = ['file_path' => '../../etc/passwd'];
        $request = new Request();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no permitida o potencialmente peligrosa');

        UploadedFileSecurity::resolveRequestFile($request, 'document', 'test');
    }

    public function testResolveRequestFileRejectsFilePathInProduction(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_POST = ['file_path' => 'C:/some/file.jpg'];
        $request = new Request();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('deshabilitada por motivos de seguridad');

        try {
            UploadedFileSecurity::resolveRequestFile($request, 'document', 'test');
        } finally {
            $_ENV['APP_ENV'] = 'testing';
        }
    }
}

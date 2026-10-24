<?php

namespace Tests\Unit\Controllers;

use App\Application\Landing\DTOs\HomeGalleryDeletedResponseDto;
use App\Application\Landing\UseCases\{GetActiveHomeGallery, ManageHomeGallery};
use App\Controllers\LandingController;
use App\Core\{Request, Response};
use App\Domain\Landing\Entities\HomeGallery;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LandingControllerTest extends TestCase
{
    private const PNG_BYTES = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private GetActiveHomeGallery $getActive;
    private ManageHomeGallery&MockObject $manage;
    private LandingController $controller;

    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void
    {
        Response::$shouldExit = false;
        $_POST = [];
        $_GET = [];
        $_FILES = [];

        $this->getActive = $this->createStub(GetActiveHomeGallery::class);
        $this->manage = $this->createMock(ManageHomeGallery::class);
        $this->controller = new LandingController($this->getActive, $this->manage);
    }

    protected function tearDown(): void
    {
        Response::$shouldExit = true;
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        foreach ($this->cleanup as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function item(int $id = 1, string $title = 'Banner'): HomeGallery
    {
        return new HomeGallery(
            id: $id,
            title: $title,
            subtitle: 'Sub',
            buttonText: 'Go',
            buttonLink: '/x',
            orderIndex: 1,
            imageUrl: 'https://cdn/x.jpg',
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00')
        );
    }

    /** @return array{0: int, 1: array<string, mixed>} */
    private function captureResponse(callable $call): array
    {
        ob_start();
        $call();
        $output = (string)ob_get_clean();
        return [http_response_code(), json_decode($output, true)];
    }

    private function fakeUpload(string $name, string $content): string
    {
        $tmp = sys_get_temp_dir() . '/landing_ctrl_' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $content);
        $this->cleanup[] = $tmp;
        $_FILES['image'] = ['name' => $name, 'type' => 'x', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
        return $tmp;
    }

    public function testIndexReturnsActiveBannersAsDtoArrays(): void
    {
        $this->getActive->method('execute')->willReturn([$this->item(1, 'A'), $this->item(2, 'B')]);

        [$code, $body] = $this->captureResponse(fn() => $this->controller->index(new Request()));

        $this->assertSame(200, $code);
        $this->assertSame('success', $body['status']);
        $this->assertSame('Galería de banners consultada exitosamente.', $body['message']);
        $this->assertCount(2, $body['data']);
        $this->assertSame($this->item(1, 'A')->toArray(), $body['data'][0]);
        $this->assertSame('B', $body['data'][1]['title']);
    }

    public function testIndexReturns500OnException(): void
    {
        $this->getActive->method('execute')->willThrowException(new Exception('boom'));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->index(new Request()));

        $this->assertSame(500, $code);
        $this->assertSame('error', $body['status']);
        $this->assertSame('boom', $body['message']);
        $this->assertSame([], $body['data']);
    }

    public function testAdminIndexReturnsAllBanners(): void
    {
        $this->manage->method('listAll')->willReturn([$this->item(3, 'C')]);

        [$code, $body] = $this->captureResponse(fn() => $this->controller->adminIndex(new Request()));

        $this->assertSame(200, $code);
        $this->assertSame('Listado administrativo de galería consultado.', $body['message']);
        $this->assertSame('C', $body['data'][0]['title']);
        $this->assertSame(3, $body['data'][0]['id']);
    }

    public function testAdminIndexReturns500OnException(): void
    {
        $this->manage->method('listAll')->willThrowException(new Exception('db down'));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->adminIndex(new Request()));

        $this->assertSame(500, $code);
        $this->assertSame('db down', $body['message']);
    }

    public function testStoreMissingTitleReturns422(): void
    {
        $this->manage->expects($this->never())->method('create');

        [$code, $body] = $this->captureResponse(fn() => $this->controller->store(new Request()));

        $this->assertSame(422, $code);
        $this->assertSame('fail', $body['status']);
        $this->assertSame('Validación fallida.', $body['message']);
        $this->assertSame(['title' => 'El título es obligatorio.'], $body['data']);
    }

    public function testStoreInvalidExtensionReturns422(): void
    {
        $_POST['title'] = 'Banner';
        $this->fakeUpload('malware.exe', 'x');
        $this->manage->expects($this->never())->method('create');

        [$code, $body] = $this->captureResponse(fn() => $this->controller->store(new Request()));

        $this->assertSame(422, $code);
        $this->assertSame('Formato no permitido.', $body['message']);
        $this->assertSame(
            ['image' => 'Extensión de archivo no permitida. Formatos aceptados: JPG, PNG, WEBP.'],
            $body['data']
        );
    }

    public function testStoreInvalidMimeReturns422(): void
    {
        $_POST['title'] = 'Banner';
        $this->fakeUpload('fake.png', '<?php echo 1;');
        $this->manage->expects($this->never())->method('create');

        [$code, $body] = $this->captureResponse(fn() => $this->controller->store(new Request()));

        $this->assertSame(422, $code);
        $this->assertSame('MIME type inválido.', $body['message']);
        $this->assertSame(['image' => 'El archivo adjunto no es una imagen válida.'], $body['data']);
    }

    public function testStoreHappyPathWithoutImageReturns201(): void
    {
        $_POST = ['title' => 'Banner', 'order_index' => '4', 'is_active' => 'false', 'button_text' => 'Go'];
        $this->manage->expects($this->once())->method('create')->with(
            'Banner', null, null, 'Go', null, 4, false, null, null
        )->willReturn($this->item(10, 'Banner'));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->store(new Request()));

        $this->assertSame(201, $code);
        $this->assertSame('success', $body['status']);
        $this->assertSame('Banner de galería creado exitosamente.', $body['message']);
        $this->assertSame(10, $body['data']['id']);
        $this->assertSame(array_keys($this->item()->toArray()), array_keys($body['data']));
    }

    public function testStoreHappyPathWithValidImageMovesFileAndPassesPath(): void
    {
        $_POST = ['title' => 'Banner'];
        $this->fakeUpload('hero.PNG', base64_decode(self::PNG_BYTES));
        $captured = null;
        $this->manage->expects($this->once())->method('create')->willReturnCallback(
            function (...$args) use (&$captured) {
                $captured = $args['localImageFilePath'] ?? $args[7];
                if (is_string($captured)) {
                    $this->cleanup[] = $captured;
                }
                return $this->item(11);
            }
        );

        [$code] = $this->captureResponse(fn() => $this->controller->store(new Request()));

        $this->assertSame(201, $code);
        $this->assertIsString($captured);
        $this->assertFileExists($captured);
        $this->assertMatchesRegularExpression('#/storage/documents/banner_[0-9a-f]{12}\.png$#', str_replace('\\', '/', $captured));
    }

    public function testStorePathDeniedReturns403(): void
    {
        $_POST = ['title' => 'Banner'];
        $this->manage->method('create')->willThrowException(
            new Exception('Acceso denegado: la ruta del archivo no está autorizada para carga de imágenes.', 403)
        );

        [$code, $body] = $this->captureResponse(fn() => $this->controller->store(new Request()));

        $this->assertSame(403, $code);
        $this->assertSame('fail', $body['status']);
        $this->assertSame('Acceso denegado: la ruta del archivo no está autorizada para carga de imágenes.', $body['message']);
        $this->assertSame([], $body['data']);
    }

    public function testStoreUnexpectedExceptionCodeFallsBackTo400(): void
    {
        $_POST = ['title' => 'Banner'];
        $this->manage->method('create')->willThrowException(new Exception('weird', 0));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->store(new Request()));

        $this->assertSame(400, $code);
        $this->assertSame('weird', $body['message']);
    }

    public function testUpdateInvalidExtensionReturns422(): void
    {
        $this->fakeUpload('x.gif', 'GIF89a');
        $this->manage->expects($this->never())->method('update');

        [$code, $body] = $this->captureResponse(fn() => $this->controller->update(new Request(), 5));

        $this->assertSame(422, $code);
        $this->assertSame('Formato no permitido.', $body['message']);
        $this->assertArrayHasKey('image', $body['data']);
    }

    public function testUpdateInvalidMimeReturns422(): void
    {
        $this->fakeUpload('x.jpg', 'not an image at all');
        $this->manage->expects($this->never())->method('update');

        [$code, $body] = $this->captureResponse(fn() => $this->controller->update(new Request(), 5));

        $this->assertSame(422, $code);
        $this->assertSame('MIME type inválido.', $body['message']);
    }

    public function testUpdateNotFoundReturns404(): void
    {
        $this->manage->method('update')->willThrowException(new Exception('Banner de galería no encontrado.', 404));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->update(new Request(), '99'));

        $this->assertSame(404, $code);
        $this->assertSame('fail', $body['status']);
        $this->assertSame('Banner de galería no encontrado.', $body['message']);
    }

    public function testUpdatePathDeniedReturns403(): void
    {
        $this->manage->method('update')->willThrowException(new Exception('Acceso denegado', 403));

        [$code] = $this->captureResponse(fn() => $this->controller->update(new Request(), 1));

        $this->assertSame(403, $code);
    }

    public function testUpdateHappyPathCastsIdAndOptionalFields(): void
    {
        $_POST = ['title' => 'Nuevo', 'order_index' => '2', 'is_active' => '0'];
        $this->manage->expects($this->once())->method('update')->with(
            7, 'Nuevo', null, null, null, null, 2, false, null, null
        )->willReturn($this->item(7, 'Nuevo'));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->update(new Request(), '7'));

        $this->assertSame(200, $code);
        $this->assertSame('Banner de galería actualizado exitosamente.', $body['message']);
        $this->assertSame('Nuevo', $body['data']['title']);
    }

    public function testUpdateWithoutOptionalFieldsPassesNulls(): void
    {
        $this->manage->expects($this->once())->method('update')->with(
            3, null, null, null, null, null, null, null, null, null
        )->willReturn($this->item(3));

        [$code] = $this->captureResponse(fn() => $this->controller->update(new Request(), 3));

        $this->assertSame(200, $code);
    }

    public function testUpdateWithValidImageCreatesStorageDirAndPassesPath(): void
    {
        $this->fakeUpload('hero.webp', base64_decode(self::PNG_BYTES));
        // MIME real es image/png pero la extensión .webp es válida; el MIME png está permitido.
        $captured = null;
        $this->manage->method('update')->willReturnCallback(
            function (...$args) use (&$captured) {
                $captured = $args['localImageFilePath'] ?? $args[8];
                if (is_string($captured)) {
                    $this->cleanup[] = $captured;
                }
                return $this->item(4);
            }
        );

        [$code] = $this->captureResponse(fn() => $this->controller->update(new Request(), 4));

        $this->assertSame(200, $code);
        $this->assertIsString($captured);
        $this->assertFileExists($captured);
        $this->assertStringEndsWith('.webp', $captured);
    }

    public function testDestroyReturnsDeletedDto(): void
    {
        $this->manage->method('delete')->willReturn(new HomeGalleryDeletedResponseDto(true, 8));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->destroy(new Request(), '8'));

        $this->assertSame(200, $code);
        $this->assertSame('success', $body['status']);
        $this->assertSame('Banner eliminado exitosamente.', $body['message']);
        $this->assertSame(['deleted' => true, 'id' => 8], $body['data']);
    }

    public function testDestroyNotFoundReturns404(): void
    {
        $this->manage->method('delete')->willThrowException(new Exception('Banner de galería no encontrado.', 404));

        [$code, $body] = $this->captureResponse(fn() => $this->controller->destroy(new Request(), 1));

        $this->assertSame(404, $code);
        $this->assertSame('Banner de galería no encontrado.', $body['message']);
        $this->assertSame([], $body['data']);
    }
}

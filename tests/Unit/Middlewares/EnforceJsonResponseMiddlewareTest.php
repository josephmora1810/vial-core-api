<?php

namespace Tests\Unit\Middlewares;

use App\Core\Request;
use App\Middlewares\EnforceJsonResponseMiddleware;
use Exception;
use PHPUnit\Framework\TestCase;

class EnforceJsonResponseMiddlewareTest extends TestCase
{
    private EnforceJsonResponseMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new EnforceJsonResponseMiddleware();
        // Limpiar variables de servidor antes de cada test
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['CONTENT_TYPE'], $_SERVER['CONTENT_LENGTH'], $_SERVER['REQUEST_METHOD']);
        $_FILES = [];
    }

    public function test_allows_request_when_no_accept_header_is_sent(): void
    {
        $request = new Request();
        $executed = false;

        $this->middleware->handle($request, function () use (&$executed) {
            $executed = true;
            return true;
        });

        $this->assertTrue($executed);
    }

    public function test_allows_request_when_accept_is_application_json_or_wildcard(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json, text/plain, */*';
        $request = new Request();
        $executed = false;

        $this->middleware->handle($request, function () use (&$executed) {
            $executed = true;
            return true;
        });

        $this->assertTrue($executed);
    }

    public function test_throws_406_when_accept_explicitly_rejects_json(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $request = new Request();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(406);
        $this->expectExceptionMessage('Esta API solo genera respuestas en formato application/json');

        $this->middleware->handle($request, fn() => true);
    }

    public function test_throws_415_when_post_has_body_without_json_content_type(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_LENGTH'] = '50';
        $_SERVER['CONTENT_TYPE'] = 'text/plain';

        $request = new Request();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(415);
        $this->expectExceptionMessage("El encabezado Content-Type debe ser 'application/json' o 'multipart/form-data'");

        $this->middleware->handle($request, fn() => true);
    }

    public function test_allows_multipart_form_data_for_file_uploads(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_LENGTH'] = '500';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=----WebKitFormBoundary';

        $request = new Request();
        $executed = false;

        $this->middleware->handle($request, function () use (&$executed) {
            $executed = true;
            return true;
        });

        $this->assertTrue($executed);
    }

    public function test_allows_valid_json_content_type_on_post(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_LENGTH'] = '20';
        $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';

        $request = new Request();
        $executed = false;

        $this->middleware->handle($request, function () use (&$executed) {
            $executed = true;
            return true;
        });

        $this->assertTrue($executed);
    }
}

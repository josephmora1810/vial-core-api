<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\{MiddlewareInterface, Pipeline, Request};
use Closure;
use PHPUnit\Framework\TestCase;

final class PipelineTest extends TestCase
{
    public function testPipelinePassesRequestThroughPipes(): void
    {
        $step1 = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next) {
                $_SERVER['test_pipeline_step1'] = true;
                return $next($request);
            }
        };

        $step2 = new class implements MiddlewareInterface {
            public function handle(Request $request, Closure $next) {
                $_SERVER['test_pipeline_step2'] = true;
                return $next($request);
            }
        };

        $request = new Request();
        $pipeline = new Pipeline();

        $result = $pipeline
            ->send($request)
            ->through([$step1, $step2])
            ->then(fn(Request $req) => 'done');

        $this->assertSame('done', $result);
        $this->assertTrue($_SERVER['test_pipeline_step1'] ?? false);
        $this->assertTrue($_SERVER['test_pipeline_step2'] ?? false);

        unset($_SERVER['test_pipeline_step1'], $_SERVER['test_pipeline_step2']);
    }
}

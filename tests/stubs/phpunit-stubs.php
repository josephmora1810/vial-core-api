<?php

namespace PHPUnit\Framework;


if (!class_exists(__NAMESPACE__ . '\\TestCase')) {
    abstract class TestCase extends Assert
    {
        protected function setUp(): void {}
        protected function tearDown(): void {}
        public function expectException(string $exception): void {}
        public function expectExceptionMessage(string $message): void {}
        public function expectExceptionCode(int|string $code): void {}
        public function createMock(string $originalClassName): object { return new \stdClass(); }
    }

    abstract class Assert
    {
        public static function assertTrue(mixed $condition, string $message = ''): void {}
        public static function assertFalse(mixed $condition, string $message = ''): void {}
        public static function assertSame(mixed $expected, mixed $actual, string $message = ''): void {}
        public static function assertEquals(mixed $expected, mixed $actual, string $message = ''): void {}
        public static function assertNotEquals(mixed $expected, mixed $actual, string $message = ''): void {}
        public static function assertNull(mixed $actual, string $message = ''): void {}
        public static function assertNotNull(mixed $actual, string $message = ''): void {}
        public static function assertCount(int $expectedCount, \Countable|iterable $haystack, string $message = ''): void {}
        public static function assertArrayHasKey(int|string $key, array|\ArrayAccess $array, string $message = ''): void {}
        public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void {}
        public static function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void {}
        public static function assertGreaterThan(mixed $expected, mixed $actual, string $message = ''): void {}
        public static function assertGreaterThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void {}
        public static function assertLessThan(mixed $expected, mixed $actual, string $message = ''): void {}
        public static function assertLessThanOrEqual(mixed $expected, mixed $actual, string $message = ''): void {}
        public static function assertIsArray(mixed $actual, string $message = ''): void {}
        public static function assertIsString(mixed $actual, string $message = ''): void {}
        public static function assertIsInt(mixed $actual, string $message = ''): void {}
        public static function assertIsFloat(mixed $actual, string $message = ''): void {}
        public static function assertIsBool(mixed $actual, string $message = ''): void {}
        public static function fail(string $message = ''): void {}
        
    }
}

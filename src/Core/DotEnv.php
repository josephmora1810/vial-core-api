<?php

namespace App\Core;

use Exception;

class DotEnv
{
	/** @var array<string, string> */
	protected array $variable = [];

	public function __construct(
		protected string $path
	) {
		if (!file_exists($path)) {
			throw new Exception("Archivo .env no se encuentra");
		}
	}

	public function load(): void
	{
		if (!is_readable($this->path)) {
			throw new Exception("El archivo {$this->path} no se puede Leer");
		}

		$lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		foreach ($lines as $line) {
			if (str_starts_with(trim($line), '#')) {
				continue;
			}

			if (str_contains($line, '=')) {
				[$name, $value] = explode('=', $line, 2);

				$name = trim($name);
				$value = trim($value, "\x00..\x1F\"");
				$this->variable[$name] = $value;

				if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
					putenv(sprintf('%s=%s', $name, $value));
					$_ENV[$name] = $value;
					$_SERVER[$name] = $value;
				}
			}
		}
	}
}
